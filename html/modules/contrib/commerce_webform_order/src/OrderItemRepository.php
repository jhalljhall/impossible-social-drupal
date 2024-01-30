<?php

namespace Drupal\commerce_webform_order;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Helps for loading and listing webform submission's order items.
 */
class OrderItemRepository implements OrderItemRepositoryInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new OrderItemRepository.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllByWebformSubmission(WebformSubmissionInterface $webform_submission, $handler_id = FALSE) {
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface[] $order_items */
    $order_item_storage = $this->entityTypeManager->getStorage('commerce_order_item');
    $properties = ['commerce_webform_order_submission' => $webform_submission->id()];
    if (!empty($order_items = $order_item_storage->loadByProperties($properties))) {
      // Filter by handler ID.
      if (!empty($handler_id)) {
        $order_items = array_filter($order_items, function ($order_item) use ($handler_id) {
          return !empty($order_item->getData('commerce_webform_order')['handler_id']) &&
            $order_item->getData('commerce_webform_order')['handler_id'] == $handler_id;
        });
      }

      return $order_items;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastByWebformSubmission(WebformSubmissionInterface $webform_submission, $handler_id = FALSE) {
    /** @var \Drupal\commerce_order\OrderItemStorageInterface $order_item_storage */
    $order_item_storage = $this->entityTypeManager->getStorage('commerce_order_item');
    $order_item_ids = $order_item_storage
      ->getQuery()
      ->condition('commerce_webform_order_submission', $webform_submission->id())
      ->sort('order_item_id', 'DESC')
      ->accessCheck(FALSE)
      ->execute();

    if (!empty($order_item_ids)) {
      foreach ($order_item_ids as $order_item_id) {
        /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
        if (!empty($order_item = $order_item_storage->load($order_item_id))) {
          // Returns the loader order item id if there is no handler ID or there
          // is a handler ID and both are the equals.
          if (empty($handler_id) ||
            (!empty($order_item->getData('commerce_webform_order')['handler_id']) &&
              $order_item->getData('commerce_webform_order')['handler_id'] == $handler_id)) {

            return $order_item;
          }
        }
      }
    }

    return NULL;
  }

}
