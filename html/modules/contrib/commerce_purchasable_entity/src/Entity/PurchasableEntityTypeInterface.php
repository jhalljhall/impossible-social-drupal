<?php

namespace Drupal\commerce_purchasable_entity\Entity;

/**
 * Defines the interface for purchasable entity types.
 */
interface PurchasableEntityTypeInterface {

  /**
   * Gets the purchasable entity type's order item type ID.
   *
   * Used for finding/creating the appropriate order item when purchasing a
   * purchasable entity (adding it to an order).
   *
   * @return string
   *   The order item type ID.
   */
  public function getOrderItemTypeId();

  /**
   * Sets the purchasable entity type's order item type ID.
   *
   * @param string $order_item_type_id
   *   The order item type ID.
   *
   * @return $this
   */
  public function setOrderItemTypeId($order_item_type_id);

}
