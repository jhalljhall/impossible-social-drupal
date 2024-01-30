<?php

namespace Drupal\Tests\commerce_webform_order\Functional\WebformHandler;

use Drupal\commerce_price\Price;
use Drupal\Tests\commerce_webform_order\Functional\CommerceWebformOrderTestBase;

/**
 * Tests Commerce Webform Order: Alter hook.
 *
 * @group commerce_webform_order
 */
class CommerceWebformOrderHandlerAlterHookTest extends CommerceWebformOrderTestBase {

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
    'cwo_test_alter_hook',
  ];

  /**
   * Order, order item and webform submission can be altered.
   *
   * In this test we are going to check if we can change the order, order item
   * or submission values using
   * hook_commerce_webform_order_handler_postsave_alter():
   *   - Order's email change.
   *   - The order item's unit price change and order total is recalculated.
   *   - Webform submission value is changed.
   *
   * @see commerce_webform_order_test_commerce_webform_order_handler_postsave_alter()
   */
  public function testAlterHook() {
    // Test as anonymous user.
    $this->drupalLogout();

    $webform_submission = $this->postProductVariationToWebform('ONE', 'cwo_test_alter_hook');
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $this->orderItemRepository->getLastByWebformSubmission($webform_submission, 'commerce_webform_order_handler');
    $order = $order_item->getOrder();

    // Check that the values have changed.
    $this->assertEquals('altered@example.com', $order->getEmail());
    $new_price = new Price('99.99', 'USD');
    $this->assertEquals($new_price->getNumber(), $order_item->getUnitPrice()->getNumber());
    $this->assertEquals($new_price->getNumber(), $order_item->getTotalPrice()->getNumber());
    $this->assertEquals($new_price->getNumber(), $order->getTotalPrice()->getNumber());
    $this->assertEquals('TWO', $webform_submission->getElementData('product'));
  }

}
