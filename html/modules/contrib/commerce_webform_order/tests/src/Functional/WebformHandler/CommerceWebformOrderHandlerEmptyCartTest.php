<?php

namespace Drupal\Tests\commerce_webform_order\Functional\WebformHandler;

use Drupal\Tests\commerce_webform_order\Functional\CommerceWebformOrderTestBase;

/**
 * Tests Commerce Webform Order handler: Empty cart.
 *
 * @group commerce_webform_order
 */
class CommerceWebformOrderHandlerEmptyCartTest extends CommerceWebformOrderTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_webform_order_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $testWebforms = [
    'cwo_test_empty_cart_1',
    'cwo_test_empty_cart_2',
  ];

  /**
   * Empty cart is disabled.
   *
   * In this test we are going to check the empty cart feature when it is
   * disabled:
   *   - After submit twice, there are two order items.
   */
  public function testEmptyCartDisabled() {
    $webform_submission = clone ($this->postProductVariationToWebform('ONE', 'cwo_test_empty_cart_1'));
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = clone ($this->orderItemRepository->getLastByWebformSubmission($webform_submission, 'commerce_webform_order_handler'));

    $this->resetCache();
    $webform_submission_2 = clone ($this->postProductVariationToWebform('TWO', 'cwo_test_empty_cart_1'));
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item_2 */
    $order_item_2 = clone ($this->orderItemRepository->getLastByWebformSubmission($webform_submission_2, 'commerce_webform_order_handler'));

    // Same order ID.
    $this->assertEquals($order_item->getOrderId(), $order_item_2->getOrderId());
    // Different order item IDs.
    $this->assertNotEquals($order_item->id(), $order_item_2->id());
    // Both order items are in the orders.
    $this->assertEquals(2, count($order_item->getOrder()->getItems()));
    $this->assertEquals(2, count($order_item_2->getOrder()->getItems()));
    $this->assertTrue($order_item->getOrder()->hasItem($order_item_2));
    $this->assertTrue($order_item_2->getOrder()->hasItem($order_item));
  }

  /**
   * Empty cart is enabled.
   *
   * In this test we are going to check the empty cart feature when it is
   * enabled:
   *   - After submit twice, there is only one order item.
   */
  public function testEmptyCartEnabled() {
    $webform_submission = clone ($this->postProductVariationToWebform('ONE', 'cwo_test_empty_cart_2'));
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = clone ($this->orderItemRepository->getLastByWebformSubmission($webform_submission, 'commerce_webform_order_handler'));

    $this->resetCache();
    $webform_submission_2 = clone ($this->postProductVariationToWebform('TWO', 'cwo_test_empty_cart_2'));
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item_2 */
    $order_item_2 = clone ($this->orderItemRepository->getLastByWebformSubmission($webform_submission_2, 'commerce_webform_order_handler'));

    // Same order ID.
    $this->assertEquals($order_item->getOrderId(), $order_item_2->getOrderId());
    // Different order item IDs.
    $this->assertNotEquals($order_item->id(), $order_item_2->id());
    // Only the last order item is in the order.
    $this->assertEquals(1, count($order_item_2->getOrder()->getItems()));
    $this->assertEquals($this->productVariations['TWO']->getSku(), $order_item_2->getPurchasedEntity()->getSku());
    $this->assertEquals($this->productVariations['TWO']->getPrice()->getNumber(), $order_item_2->getTotalPrice()->getNumber());
    $this->assertEquals($this->productVariations['TWO']->getPrice()->getNumber(), $order_item_2->getOrder()->getTotalPrice()->getNumber());
  }

}
