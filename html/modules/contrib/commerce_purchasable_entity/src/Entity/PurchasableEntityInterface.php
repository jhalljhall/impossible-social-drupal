<?php

namespace Drupal\commerce_purchasable_entity\Entity;

use Drupal\commerce\PurchasableEntityInterface as PurchasableEntityBaseInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_store\Entity\EntityStoresInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a purchasable entity type.
 */
interface PurchasableEntityInterface extends PurchasableEntityBaseInterface, EntityStoresInterface, EntityPublishedInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the title.
   *
   * @return string
   *   The title
   */
  public function getTitle();

  /**
   * Sets the title.
   *
   * @param string $title
   *   The title.
   *
   * @return $this
   */
  public function setTitle($title);

  /**
   * Get the SKU.
   *
   * @return string
   *   The SKU.
   */
  public function getSku();

  /**
   * Set the SKU.
   *
   * @param string $sku
   *   The SKU.
   *
   * @return $this
   */
  public function setSku($sku);

  /**
   * Sets the price.
   *
   * @param \Drupal\commerce_price\Price $price
   *   The price.
   *
   * @return $this
   */
  public function setPrice(Price $price);

  /**
   * Gets the creation timestamp.
   *
   * @return int
   *   The creation timestamp.
   */
  public function getCreatedTime();

  /**
   * Sets the creation timestamp.
   *
   * @param int $timestamp
   *   The creation timestamp.
   *
   * @return $this
   */
  public function setCreatedTime($timestamp);

}
