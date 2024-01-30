<?php

/**
 * @file
 * Hooks provided by the Commerce Webform Order module.
 */

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Alter the order, order item and webform submission on the webform handler.
 *
 * This hook is called in the webform handler and allows modules to alter the
 * order, order item and webform submission just before they're saved.
 *
 * @param \Drupal\commerce_order\Entity\OrderInterface $order
 *   The commerce order entity.
 * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
 *   The commerce order_item entity.
 * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
 *   The webform submission.
 *
 * @see \Drupal\commerce_webform_order\Plugin\WebformHandler\CommerceWebformOrderHandler::postSave()
 *
 * @ingroup commerce_webform_order_api
 */
function hook_commerce_webform_order_handler_postsave_alter(OrderInterface $order, OrderItemInterface $order_item, WebformSubmissionInterface $webform_submission) {
  // Add some data to the order.
  $order->setData('sashay', 'away');
}
