<?php

namespace Drupal\Tests\commerce_webform_order\Functional\WebformHandler;

use Drupal\Tests\commerce_webform_order\Functional\CommerceWebformOrderTestBase;

/**
 * Tests Commerce Webform Order handler: Combine cart.
 *
 * @group commerce_webform_order
 */
class CommerceWebformOrderHandlerCombineCartTest extends CommerceWebformOrderTestBase {

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
    'cwo_test_combine_cart_1',
    'cwo_test_combine_cart_2',
  ];

  /**
   * Combine cart is disabled.
   *
   * In this test we are going to check the combine cart feature when it is
   * disabled:
   *   - After submit twice, the same product, there are two order items.
   */
  public function testCombineCartDisabled() {
    $webform_submission = clone ($this->postProductVariationToWebform('ONE', 'cwo_test_combine_cart_1'));
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = clone ($this->orderItemRepository->getLastByWebformSubmission($webform_submission, 'commerce_webform_order_handler'));

    $this->resetCache();
    $webform_submission_2 = clone ($this->postProductVariationToWebform('ONE', 'cwo_test_combine_cart_1'));
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
   * Combine cart is enabled.
   *
   * In this test we are going to check the combine cart feature when it is
   * enabled:
   *   - After submit twice, the same product, there is only one order item.
   */
  public function testCombineCartEnabled() {
    $webform_submission = clone ($this->postProductVariationToWebform('ONE', 'cwo_test_combine_cart_2'));
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = clone ($this->orderItemRepository->getLastByWebformSubmission($webform_submission, 'commerce_webform_order_handler'));

    $this->resetCache();
    $webform_submission_2 = clone ($this->postProductVariationToWebform('ONE', 'cwo_test_combine_cart_2'));
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item_2 */
    $order_item_2 = clone ($this->orderItemRepository->getLastByWebformSubmission($webform_submission_2, 'commerce_webform_order_handler'));

    // Same order ID.
    $this->assertEquals($order_item->getOrderId(), $order_item_2->getOrderId());
    // Same order item IDs.
    $this->assertEquals($order_item->id(), $order_item_2->id());
    // Only the last order item is in the order.
    $this->assertEquals(1, count($order_item_2->getOrder()->getItems()));
    $this->assertEquals('2.00', $order_item_2->getQuantity());
    $this->assertEquals($this->productVariations['ONE']->getPrice()->getNumber() * 2, $order_item_2->getTotalPrice()->getNumber());
  }

}
