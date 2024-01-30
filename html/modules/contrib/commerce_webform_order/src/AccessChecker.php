<?php

namespace Drupal\commerce_webform_order;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Checks webform submission access.
 */
class AccessChecker implements AccessCheckerInterface {

  /**
   * The order item repository.
   *
   * @var \Drupal\commerce_webform_order\OrderItemRepositoryInterface
   */
  protected $orderItemRepository;

  /**
   * Constructs a new AccessChecker.
   *
   * @param \Drupal\commerce_webform_order\OrderItemRepositoryInterface $order_item_repository
   *   The order item repository.
   */
  public function __construct(OrderItemRepositoryInterface $order_item_repository) {
    $this->orderItemRepository = $order_item_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function updateAccess(WebformSubmissionInterface $webform_submission, AccountInterface $account) {
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface[] $order_items */
    $order_item = $this->orderItemRepository->getLastByWebformSubmission($webform_submission);

    if ($order_item) {
      $data = $order_item->getData('commerce_webform_order', []);

      if (!empty($data['prevent_update']) && $order_item->getOrder()->getState()->getId() != 'draft') {
        return AccessResult::forbidden();
      }
    }

    // No opinion.
    return AccessResult::neutral();
  }

}
