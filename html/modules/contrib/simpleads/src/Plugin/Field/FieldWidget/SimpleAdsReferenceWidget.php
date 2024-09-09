<?php

namespace Drupal\simpleads\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simpleads\Entity\Advertisement;

/**
 * Plugin implementation of the 'simpleads_reference' widget.
 *
 * @FieldWidget(
 *   id = "simpleads_reference",
 *   label = @Translation("SimpleAds Reference"),
 *   field_types = {
 *     "simpleads_reference"
 *   }
 * )
 */
class SimpleAdsReferenceWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $default_value = NULL;

    // Build a "label (swid)' value that can be parse for storage.
    if (!empty($items[$delta]->target_id)) {
      $simpleads = Advertisement::load($items[$delta]->target_id);
      $default_value = sprintf('%s (%d)', $simpleads->label(), $items[$delta]->target_id);
    }

    $element['data'] = [
      '#title' => $this->t('SimpleAds reference'),
      '#type' => 'textfield',
      '#autocomplete_route_name' => 'simpleads.autocomplete',
      '#autocomplete_route_parameters' => [],
      '#placeholder' => t('Start typing the ad name'),
      '#default_value' => $default_value,
      '#element_validate' => [
        [static::class, 'validate'],
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $item = NULL;
    foreach ($values as $delta => &$item) {
      $item['delta'] = $delta;
      if (preg_match('/(.+\\s)\\(([^\\)]+)\\)/', $item['data'], $matches)) {
        $item['target_id'] = trim($matches[2]);
      }
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public static function validate($element, FormStateInterface $form_state) {
    $value = $element['#value'];
    if (!EntityAutocomplete::extractEntityIdFromAutocompleteInput($value)) {
      $form_state->setValueForElement($element, '');
      return;
    }
  }

}
