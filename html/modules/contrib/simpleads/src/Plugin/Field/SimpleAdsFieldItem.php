<?php

namespace Drupal\simpleads\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * A computed property for processing SimpleAds advertisement.
 */
class SimpleAdsFieldItem extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    if ($entity = $this->getEntity()) {
      $this->list[0] = $this->createItem(0, $entity);
    }
  }

}
