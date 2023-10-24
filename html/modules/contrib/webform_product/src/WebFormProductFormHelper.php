<?php

namespace Drupal\webform_product;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\WebformInterface;

/**
 * Webform product form helper.
 */
class WebFormProductFormHelper {

  /**
   * Process element form.
   *
   * @param array $element
   *   Form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param array $complete_form
   *   Complete form.
   *
   * @return array
   *   Form element.
   */
  public static function processElementForm(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $element_info = $form_state->getFormObject()->getElement();
    $webform = self::getWebformInFormState($form_state);

    $hasWebformProductHandler = FALSE;

    foreach ($webform->getHandlers() as $handler) {
      if ($handler->getPluginId() == 'webform_product' && $handler->isEnabled()) {
        $hasWebformProductHandler = TRUE;
      }
    }

    // @todo fix this when Price* webform elements are working.
    //   Only change the form of price_* webform elements.
    //    if (strpos($element_info['#type'], 'price_', 0) === FALSE) {
    //      return $element;
    //    }
    // @todo Only add price field webform handler for product is enabled.
    if ($hasWebformProductHandler) {
      $element['price'] = [
        '#type' => 'textfield',
        '#title' => t('Price'),
        '#placeholder' => '99.99',
        '#maxlength' => 20,
        '#default_value' => static::getSetting($form_state, 'top'),
        '#element_validate' => [[get_class(), 'saveTopPrice']],
        '#description' => t('Use this to add an extra order item to the order, this can be used as a supplement with the options or single without the prices of the options.'),
      ];
    }

    return $element;
  }

  /**
   * Save top price.
   *
   * @param array $element
   *   Form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public static function saveTopPrice(array $element, FormStateInterface $form_state) {
    static::setSetting($form_state, 'top', $element['#value']);
  }

  /**
   * Get setting.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param string $settingKey
   *   Setting key.
   *
   * @return mixed|null
   *   Setting.
   */
  public static function getSetting(FormStateInterface $form_state, $settingKey) {
    $webform = self::getWebformInFormState($form_state);

    if ($webform) {
      $formObject = $form_state->getFormObject();
      $setting = $webform->getThirdPartySetting('webform_product', $formObject->getKey());
    }

    return $setting[$settingKey] ?? NULL;
  }

  /**
   * Set setting.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param string $settingKey
   *   Setting key.
   * @param mixed $value
   *   Value.
   */
  public static function setSetting(FormStateInterface $form_state, $settingKey, $value) {
    $webform = self::getWebformInFormState($form_state);

    if ($webform) {
      $formObject = $form_state->getFormObject();
      $elementKey = $formObject->getKey();
      if (empty($elementKey)) {
        // It could be a new element addition, then the getKey will be empty.
        $form_values = $form_state->getValues();
        if (!empty($form_values['key'])) {
          $elementKey = $form_values['key'];
        }
        else {
          // We should not proceed if the key is empty.
          return;
        }
      }
      $setting = $webform->getThirdPartySetting('webform_product', $elementKey);
      $setting[$settingKey] = $value;
      $webform->setThirdPartySetting('webform_product', $elementKey, $setting);
    }
  }

  /**
   * Get webform in form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return \Drupal\webform\WebformInterface|null
   *   Webform object.
   */
  private static function getWebformInFormState(FormStateInterface $form_state) {
    /** @var \Drupal\webform_ui\Form\WebformUiElementEditForm $formObject */
    $formObject = $form_state->getFormObject();

    $webformObject = method_exists($formObject, 'getWebform') ? $formObject->getWebform() : NULL;

    return ($webformObject instanceof WebformInterface) ? $webformObject : NULL;
  }

}
