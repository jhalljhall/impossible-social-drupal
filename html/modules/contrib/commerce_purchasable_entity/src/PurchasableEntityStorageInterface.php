<?php

namespace Drupal\commerce_purchasable_entity;

use Drupal\Core\Entity\ContentEntityStorageInterface;

/**
 * Defines the interface for purchasable entity storage.
 */
interface PurchasableEntityStorageInterface extends ContentEntityStorageInterface {

  /**
   * Loads the purchasable entity for the given SKU.
   *
   * @param string $sku
   *   The SKU.
   *
   * @return \Drupal\commerce_purchasable_entity\Entity\PurchasableEntityInterface|null
   *   The purchasable entity, or NULL if not found.
   */
  public function loadBySku($sku);

}
