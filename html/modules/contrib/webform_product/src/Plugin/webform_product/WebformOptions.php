<?php

namespace Drupal\webform_product\Plugin\webform_product;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\webform\Utility\WebformYaml;
use Drupal\webform_product\WebFormProductFormHelper;

/**
 * Webform extra options.
 *
 * @PluginID("webform_options")
 */
class WebformOptions {

  /**
   * Process webform form element.
   *
   * @param array $element
   *   Form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return array
   *   Form element.
   */
  public static function process(array &$element, FormStateInterface $form_state) {
    // @todo fix this when Price* webform elements are working.
    // Check for price_* elements, skip the check for Option definitions.
    //    if (method_exists($form_state->getFormObject(), 'getElement')) {
    //      $element_info = $form_state->getFormObject()->getElement();
    //
    //      // Only change the form of price_* webform elements.
    //      if (strpos($element_info['#type'], 'price_', 0) === FALSE) {
    //        return $element;
    //      }
    //    }
    //    else {
    //      return $element;
    //    }
    // For options with optgroup display a CodeMirror YAML editor.
    if (!empty($element['#yaml']) || (isset($element['#default_value']) && is_array($element['#default_value']) && static::hasOptGroup($element['#default_value']))) {
      $element['price'] = [
        '#type' => 'webform_codemirror',
        '#mode' => 'yaml',
        '#placeholder' => t('Enter price options…'),
        '#description' => t('Key-price pairs MUST be specified as "price: Numbers". Use of only alphanumeric characters and underscores is recommended in keys. One option per line.'),
      ];

      if ($prices = WebFormProductFormHelper::getSetting($form_state, 'options')) {
        $buildInfo = $form_state->getBuildInfo();
        /** @var \Drupal\webform_ui\Form\WebformUiElementEditForm $callback */
        $callback = $buildInfo['callback_object'];
        $parent_element = $callback->getElement();
        $parent_element_keys = array_keys($parent_element['#element']);

        $delta = static::getCompositeDelta($element['#name']);
        if (isset($parent_element_keys[$delta])) {
          if (isset($prices[$parent_element_keys[$delta]])) {
            $element['price']['#default_value'] = WebformYaml::encode($prices[$parent_element_keys[$delta]]);
          }
        }
      }
    }
    else {
      $element['options']['#element']['price'] = [
        '#type' => 'textfield',
        '#title' => t('Price'),
        '#title_display' => 'invisible',
        '#placeholder' => '99.99',
        '#maxlength' => 20,
      ];

      if ($prices = WebFormProductFormHelper::getSetting($form_state, 'options')) {
        foreach ($element['options']['#default_value'] as $delta => $row) {
          if (isset($prices[$delta])) {
            $element['options']['#default_value'][$delta]['price'] = $prices[$delta];
          }
        }
      }
    }

    // WebFormOptions::convertValuesToOptions() destroys our values so do
    // something about that.
    array_unshift($element['#element_validate'], [
      get_class(),
      'convertToSettings',
    ]);

    return $element;
  }

  /**
   * Convert to settings.
   *
   * @param array $element
   *   Form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   From state.
   */
  public static function convertToSettings(array $element, FormStateInterface $form_state) {
    $options = (is_string($element['options']['#value'])) ? Yaml::decode($element['options']['#value']) : $element['options']['#value'];

    if (isset($element['price'])) {
      $values = $form_state->getValues();
      $parents = [];
      foreach ($element['#parents'] as $item) {
        if (is_numeric($item)) {
          break;
        }
        $parents[] = $item;
      }
      $compositeItems = NestedArray::getValue($values, $parents);

      // Search component delta:
      $delta = static::getCompositeDelta($element['#name']);
      $prices = WebFormProductFormHelper::getSetting($form_state, 'options');
      $price = (is_string($element['price']['#value'])) ? Yaml::decode($element['price']['#value']) : $element['price']['#value'];

      foreach ($options as $key => $value) {
        if (!empty($price[$key])) {
          // Populate empty option value or option text.
          if ($key === '') {
            $key = $value;
          }
          $prices[$compositeItems[$delta]['key']][$key] = $price[$key];
        }
      }
    }
    else {
      foreach ($options as $key => $value) {
        if (isset($value['price'])) {
          $option_value = $key;
          $option_text = $value['text'];

          // Populate empty option value or option text.
          if ($option_value === '') {
            $option_value = $option_text;
          }
          $prices[$option_value] = $value['price'];
        }
      }
    }

    if (isset($prices) && !empty($prices)) {
      WebFormProductFormHelper::setSetting($form_state, 'options', $prices);
    }
  }

  /**
   * Determine if options array contains an OptGroup.
   *
   * @param array $options
   *   An array of options.
   *
   * @return bool
   *   TRUE if options array contains an OptGroup.
   */
  public static function hasOptGroup(array $options) {
    foreach ($options as $option_text) {
      if (is_array($option_text)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Search composite element delta value.
   *
   * @param string $name
   *   Form element #name.
   *
   * @return mixed
   *   Composite element delta or NULL.
   */
  private static function getCompositeDelta(string $name) {
    $delta = NULL;
    $element_name = explode('[', str_replace(']', '', $name));
    foreach ($element_name as $item) {
      if (is_numeric($item)) {
        $delta = $item;
        break;
      }
    }

    return $delta;
  }

}
