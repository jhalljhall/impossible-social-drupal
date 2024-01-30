<?php

namespace Drupal\commerce_purchasable_entity\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the purchasable entity type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "commerce_purchasable_entity_type",
 *   label = @Translation("Purchasable Entity type"),
 *   label_collection = @Translation("Purchasable Entity types"),
 *   label_singular = @Translation("purchasable entity type"),
 *   label_plural = @Translation("commerce purchasable entities types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count commerce purchasable entities type",
 *     plural = "@count commerce purchasable entities types",
 *   ),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\commerce_purchasable_entity\Form\PurchasableEntityTypeForm",
 *       "edit" = "Drupal\commerce_purchasable_entity\Form\PurchasableEntityTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "list_builder" = "Drupal\commerce_purchasable_entity\PurchasableEntityTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   admin_permission = "administer commerce_purchasable_entity_type",
 *   bundle_of = "commerce_purchasable_entity",
 *   config_prefix = "commerce_purchasable_entity_type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/commerce/config/purchasable-entities/types/add",
 *     "edit-form" = "/admin/commerce/config/purchasable-entities/types/manage/{commerce_purchasable_entity_type}",
 *     "delete-form" = "/admin/commerce/config/purchasable-entities/types/manage/{commerce_purchasable_entity_type}/delete",
 *     "collection" = "/admin/commerce/config/purchasable-entities/types"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "uuid",
 *     "orderItemType",
 *   }
 * )
 */
class PurchasableEntityType extends ConfigEntityBundleBase implements PurchasableEntityTypeInterface {

  /**
   * The machine name of this purchasable entity type.
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable name of the purchasable entity type.
   *
   * @var string
   */
  protected $label;

  /**
   * The order item type ID.
   *
   * @var string
   */
  protected $orderItemType;

  /**
   * {@inheritdoc}
   */
  public function getOrderItemTypeId() {
    return $this->orderItemType;
  }

  /**
   * {@inheritdoc}
   */
  public function setOrderItemTypeId($order_item_type_id) {
    $this->orderItemType = $order_item_type_id;

    return $this;
  }

}
