<?php

namespace Drupal\simpleads\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'simpleads_stats' widget.
 *
 * @FieldWidget(
 *   id = "simpleads_stats",
 *   label = @Translation("SimpleAds Statistics"),
 *   field_types = {
 *     "simpleads_stats"
 *   }
 * )
 */
class SimpleAdsStatsWidget extends WidgetBase {

  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    return $element;
  }

}
