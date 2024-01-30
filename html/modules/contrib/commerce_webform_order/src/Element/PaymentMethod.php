<?php

namespace Drupal\commerce_webform_order\Element;

use Drupal\commerce_payment\Entity\PaymentMethod as PaymentMethodEntity;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\PaymentOption;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsCreatingPaymentMethodsInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\webform\Element\WebformCompositeBase;

/**
 * Provides a form element for embedding the payment gateway forms.
 *
 * @FormElement("commerce_webform_order_payment_method")
 */
class PaymentMethod extends WebformCompositeBase {

  /**
   * {@inheritdoc}
   */
  public static function getCompositeElements(array $element) {
    $elements = [];
    $elements['payment_gateway'] = [
      '#type' => $element['#payment_gateway__type'] ?? 'radios',
      '#title' => t('Payment gateway'),
      '#options' => [],
    ];
    $elements['payment_method'] = [
      '#type' => 'value',
      '#title' => t('Payment method'),
    ];
    $elements['billing_profile'] = [
      '#type' => 'value',
      '#title' => t('Billing profile'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function processWebformComposite(&$element, FormStateInterface $form_state, &$complete_form) {
    if (isset($element['#initialize'])) {
      return $element;
    }

    $element = parent::processWebformComposite($element, $form_state, $complete_form);
    $wrapper_id = Html::getUniqueId($element['#id']);
    $element['#attributes']['id'] = $wrapper_id;
    $element['#attributes']['class'][] = 'commerce-webform-order--payment-method';
    $element['#attributes']['class'][] = 'js-commerce-webform-order--payment-method';

    /** @var \Drupal\commerce_payment\PaymentGatewayStorageInterface $payment_gateway_storage */
    $payment_gateway_storage = \Drupal::entityTypeManager()->getStorage('commerce_payment_gateway');
    $gateway_query = $payment_gateway_storage->getQuery()
      ->condition('status', TRUE);

    if (!empty($element['#allowed_payment_gateways'])) {
      $gateway_query->condition('id', $element['#allowed_payment_gateways'], 'IN');
    }

    $gateway_ids = $gateway_query->execute();

    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface[] $payment_gateways */
    $payment_gateways = $payment_gateway_storage->loadMultiple($gateway_ids);

    // Core bug #1988968 doesn't allow the payment method add form JS to depend
    // on an external library, so the libraries need to be preloaded here.
    foreach ($payment_gateways as $payment_gateway) {
      if (($js_library = $payment_gateway->getPlugin()->getJsLibrary()) !== NULL) {
        $element['#attached']['library'][] = $js_library;
      }
    }

    /** @var \Drupal\commerce_payment\PaymentOption[] $options */
    $options = \Drupal::service('commerce_webform_order.options_builder')->buildOptions($payment_gateways);
    $option_labels = array_map(function (PaymentOption $option) {
      return $option->getLabel();
    }, $options);

    $default_option_id = $element['payment_gateway']['#default_value'] ?? NULL;
    // Use the default option if it is set.
    if ($default_option_id && isset($options[$default_option_id])) {
      $default_option = $options[$default_option_id];
    }
    // If not exists, try to use the first gateway ID coincidence.
    elseif ($default_option_id) {
      foreach ($options as $option) {
        if ($option->getPaymentGatewayId() == $default_option_id) {
          $default_option = $option;
          break;
        }
      }
    }
    // Always use de first value as fallback value.
    if (empty($default_option_id)) {
      $default_option = reset($options);
    }

    $element['#after_build'][] = [get_class(), 'clearValues'];
    $element['payment_gateway']['#options'] = $option_labels;
    $element['payment_gateway']['#ajax'] = [
      'callback' => [get_class(), 'ajaxRefresh'],
      'wrapper' => $wrapper_id,
    ];

    // Add a class to each individual radio, to help themers.
    if ($element['payment_gateway']['#type'] === 'radios') {
      foreach ($options as $option) {
        $class_name = $option->getPaymentMethodId() ? 'stored' : 'new';
        $element['payment_gateway'][$option->getId()]['#attributes']['class'][] = "payment-method--$class_name";
      }
    }

    if (!empty($default_option)) {
      // Update the default value.
      $element['payment_gateway']['#default_value'] = $default_option->getId();
      $element['payment_method']['#default_value'] = $default_option->getPaymentMethodId();
      if ($default_option->getPaymentMethodId()) {
        try {
          if ($payment_method = PaymentMethodEntity::load($default_option->getPaymentMethodId())) {
            $element['billing_profile']['#default_value'] = $payment_method->id();
          }
        }
        catch (\Exception $exception) {
          // Just try to load the stored payment method.
        }
      }

      $default_payment_gateway_id = $default_option->getPaymentGatewayId();
      $payment_gateway = $payment_gateways[$default_payment_gateway_id];
      $payment_gateway_plugin = $payment_gateway->getPlugin();

      // If this payment gateway plugin supports creating tokenized payment
      // methods before processing payment, we build the "add-payment-method"
      // plugin form.
      if (empty($default_option->getPaymentMethodId()) && $payment_gateway_plugin instanceof SupportsCreatingPaymentMethodsInterface) {
        $element = self::buildPaymentMethodForm($element, $form_state, $default_option);
      }
    }

    // Ensure antibot module does not block this submission.
    // At this point, the form has already been validated by antibot,
    // so we can deactivate the validation.
    // Reloading the form via ajax during the payment gateway selection,
    // re-initializes the form and re-adds the antibot validation, this should
    // only happen when loading the first step.
    // @see antibot_webform_submission_form_alter()
    // @see antibot_form_validation()
    $current_page = $form_state->get('current_page');
    $pages = $form_state->get('pages') ?? [];
    if (!empty($current_page) && !empty($pages) &&
      array_key_first($pages) !== $current_page) {
      $form_state->setSubmitted();
    }

    return $element;
  }

  /**
   * Validates a composite element.
   */
  public static function validateWebformComposite(&$element, FormStateInterface $form_state, &$complete_form) {
    parent::validateWebformComposite($element, $form_state, $complete_form);

    $value = NestedArray::getValue($form_state->getValues(), $element['#parents']);

    // When the user selects an existing payment method,
    // we must update the values, since the payment gateway selector uses
    // the method key instead of the gateway key.
    // @see \Drupal\commerce_webform_order\PaymentOptionsBuilder::buildOptions()
    if (!empty($value['payment_gateway']) && is_numeric($value['payment_gateway'])) {
      /** @var \Drupal\commerce_payment\PaymentMethodStorageInterface $payment_method_storage */
      $payment_method_storage = \Drupal::entityTypeManager()->getStorage('commerce_payment_method');

      /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
      $payment_method = $payment_method_storage->load($value['payment_gateway']);
      $value = [
        'payment_gateway' => $payment_method->getPaymentGatewayId(),
        'payment_method' => $payment_method->id(),
        'billing_profile' => $payment_method->getBillingProfile(),
      ];
      $element['#value'] = $value;
      $form_state->setValueForElement($element, $value);
    }
  }

  /**
   * Builds the payment method form for the selected payment option.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the parent form.
   * @param \Drupal\commerce_payment\PaymentOption $payment_option
   *   The payment option.
   *
   * @return array
   *   The modified pane form.
   */
  protected static function buildPaymentMethodForm(array $element, FormStateInterface $form_state, PaymentOption $payment_option) {
    if (!empty($element['#states'])) {
      /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
      $webform_submission = $form_state->getFormObject()->getEntity();
      $conditions_validator = \Drupal::service('webform_submission.conditions_validator');
      // Validation and submission should be occurred only when the element is
      // accessible, in multistep forms this element is not accessible in hidden
      // steps.
      if (!$conditions_validator->isElementEnabled($element, $webform_submission) || !$conditions_validator->isElementVisible($element, $webform_submission)) {
        return $element;
      }
    }

    /** @var \Drupal\commerce_payment\PaymentMethodStorageInterface $payment_method_storage */
    $payment_method_storage = \Drupal::entityTypeManager()->getStorage('commerce_payment_method');

    $payment_method = $payment_method_storage->createForCustomer(
      $payment_option->getPaymentMethodTypeId(),
      $payment_option->getPaymentGatewayId(),
      \Drupal::currentUser()->id()
    );

    /** @var \Drupal\commerce\InlineFormManager $inline_form_manager */
    $inline_form_manager = \Drupal::service('plugin.manager.commerce_inline_form');
    $inline_form = $inline_form_manager->createInstance('payment_gateway_form', [
      'operation' => 'add-payment-method',
    ], $payment_method);

    $element['payment_method'] = [
      '#parents' => array_merge($element['#array_parents'], ['payment_method']),
      '#inline_form' => $inline_form,
    ];
    $element['payment_method'] = $inline_form->buildInlineForm($element['payment_method'], $form_state);

    $element['#element_validate'][] = [get_class(), 'validatePaymentMethodForm'];
    unset(
      $element['payment_method']['#element_validate'],
      $element['payment_method']['billing_information']['#element_validate']
    );
    $element['payment_method']['#commerce_element_submit'] = [
      [get_class(), 'submitPaymentMethodForm'],
    ];

    return $element;
  }

  /**
   * Runs the inline form validation.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function validatePaymentMethodForm(array &$element, FormStateInterface $form_state) {
    /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\InlineFormInterface $plugin */
    $plugin = $element['payment_method']['#inline_form'];
    $plugin->validateInlineForm($element['payment_method'], $form_state);

    if (!empty($element['payment_method']['billing_information'])) {
      /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\InlineFormInterface $plugin */
      $plugin = $element['payment_method']['billing_information']['#inline_form'];
      $plugin->validateInlineForm($element['payment_method']['billing_information'], $form_state);
    }
  }

  /**
   * Runs the inline form submission.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function submitPaymentMethodForm(array &$element, FormStateInterface $form_state) {
    /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\InlineFormInterface $plugin */
    $plugin = $element['#inline_form'];
    $plugin->submitInlineForm($element, $form_state);

    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $plugin->getEntity();
    $payment_method_id = NULL;
    if ($payment_method instanceof PaymentMethodInterface) {
      $payment_method_id = $payment_method->id();
    }

    $billing_profile_id = NULL;
    if (!empty($element['payment_method']['billing_information'])) {
      /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\InlineFormInterface $plugin */
      $plugin = $element['payment_method']['billing_information']['#inline_form'];
      $plugin->submitInlineForm($element['payment_method']['billing_information'], $form_state);

      /** @var \Drupal\profile\Entity\ProfileInterface $billing_profile */
      $billing_profile = $plugin->getEntity();
      if ($billing_profile instanceof ProfileInterface) {
        $billing_profile_id = $billing_profile->id();

        if ($payment_method instanceof PaymentMethodInterface) {
          $payment_method->setBillingProfile($billing_profile);
        }
      }
    }

    // Ensure payment gateway and method are stored.
    $parents = array_slice($element['#array_parents'], -2, 1);
    $values = $form_state->getValue($parents);
    $values['payment_gateway'] = $payment_method->getPaymentGatewayId();
    $values['payment_method'] = $payment_method_id;
    $values['billing_profile'] = $billing_profile_id;
    $form_state->setValue($parents, $values);
  }

  /**
   * Ajax callback.
   */
  public static function ajaxRefresh(array $form, FormStateInterface $form_state) {
    $parents = array_slice($form_state->getTriggeringElement()['#array_parents'], 0, -2);

    return NestedArray::getValue($form, $parents);
  }

  /**
   * Clears dependent form input when the payment_method changes.
   *
   * Without this Drupal considers the rebuilt form to already be submitted,
   * ignoring default values.
   */
  public static function clearValues(array $element, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    if (!$triggering_element) {
      return $element;
    }

    $parents = array_slice($triggering_element['#array_parents'], 0, -1);
    $triggering_element_name = end($parents);
    if ($triggering_element_name == 'payment_gateway') {
      $user_input = &$form_state->getUserInput();
      $element_input = NestedArray::getValue($user_input, $element['#array_parents']);
      unset($element_input['payment_method']);
      NestedArray::setValue($user_input, $element['#array_parents'], $element_input);
    }

    return $element;
  }

}
