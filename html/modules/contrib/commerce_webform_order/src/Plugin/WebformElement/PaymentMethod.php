<?php

namespace Drupal\commerce_webform_order\Plugin\WebformElement;

use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformElement\WebformCompositeBase;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Provides a form element for embedding the payment gateway forms.
 *
 * @WebformElement(
 *   id = "commerce_webform_order_payment_method",
 *   label = @Translation("Payment Method"),
 *   description = @Translation("Provides a form element for embedding the payment method selector."),
 *   category = @Translation("Commerce"),
 *   multiline = TRUE,
 *   composite = TRUE,
 *   states_wrapper = TRUE,
 *   dependencies = {
 *     "commerce_payment",
 *   },
 * )
 */
class PaymentMethod extends WebformCompositeBase {

  /**
   * {@inheritdoc}
   */
  protected function defineDefaultProperties() {
    $properties = [
      'default_value' => '',
      'allowed_payment_gateways' => [],
    ] + parent::defineDefaultProperties();

    unset(
      $properties['multiple'],
      $properties['multiple__add'],
      $properties['multiple__add_more'],
      $properties['multiple__add_more_button_label'],
      $properties['multiple__add_more_input'],
      $properties['multiple__add_more_input_label'],
      $properties['multiple__add_more_items'],
      $properties['multiple__empty_items'],
      $properties['multiple__header'],
      $properties['multiple__header_label'],
      $properties['multiple__item_label'],
      $properties['multiple__min_items'],
      $properties['multiple__no_items_message'],
      $properties['multiple__operations'],
      $properties['multiple__remove'],
      $properties['multiple__sorting'],
      $properties['format_items'],
      $properties['format_items_html'],
      $properties['format_items_text'],
    );

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    // Don't allow manual values, only predefined ones, so we remove the
    // "other" types.
    $form['composite']['element']['payment_gateway']['settings']['data']['payment_gateway__type']['#options'] = [
      'radios' => $this->t('Radios'),
      'select' => $this->t('Select'),
    ];

    $form['element']['select_message'] = [
      '#type' => 'webform_message',
      '#message_type' => 'warning',
      '#message_message' => $this->t('In order to use this element in a Webform, we need to replace the checkout pane "Payment process" by the one provided by this module "Payment process (Commerce Webform Order)", in the corresponding checkout flow, and probably you will want to disable the "Payment information" pane.'),
      '#access' => TRUE,
    ];
    $form['element']['allowed_payment_gateways'] = [
      '#type' => 'select',
      '#title' => $this->t('Allowed payment gateways'),
      '#options' => $this->getPaymentGateways(),
      '#multiple' => TRUE,
    ];

    return $form;
  }

  /**
   * Prepare #options array of commerce payment gateways.
   *
   * @return array
   *   Prepared array of commerce payment gateways.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getPaymentGateways() {
    $options = [];

    /** @var \Drupal\commerce_payment\PaymentGatewayStorageInterface $payment_gateway_storage */
    $payment_gateway_storage = $this->entityTypeManager->getStorage('commerce_payment_gateway');

    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface[] $payment_gateways */
    $payment_gateways = $payment_gateway_storage->loadByProperties(['status' => TRUE]);
    uasort($payment_gateways, [PaymentGateway::class, 'sort']);
    foreach ($payment_gateways as $key => $payment_gateway) {
      $options[$key] = $payment_gateway->label();
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  protected function formatHtmlItemValue(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    return $this->formatTextItemValue($element, $webform_submission, $options);
  }

  /**
   * {@inheritdoc}
   */
  protected function formatTextItemValue(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    $value = $this->getValue($element, $webform_submission, $options);

    $lines = [];
    if (!empty($value['payment_gateway'])) {
      $lines['payment_gateway'] = $value['payment_gateway'];
    }
    if (!empty($value['payment_method'])) {
      $lines['payment_method'] = $value['payment_method'];
    }
    if (!empty($value['billing_profile'])) {
      $lines['billing_profile'] = $value['billing_profile'];
    }

    return $lines;
  }

}
