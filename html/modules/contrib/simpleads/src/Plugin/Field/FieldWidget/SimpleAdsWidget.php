<?php

namespace Drupal\simpleads\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'simpleads_advertisement' widget.
 *
 * @FieldWidget(
 *   id = "simpleads_advertisement",
 *   label = @Translation("SimpleAds Advertisement"),
 *   field_types = {
 *     "simpleads_advertisement"
 *   }
 * )
 */
class SimpleAdsWidget extends WidgetBase {

  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    return $element;
  }

}
