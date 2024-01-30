<?php

namespace Drupal\Tests\commerce_webform_order\Functional\WebformHandler;

use Drupal\Tests\commerce_webform_order\Functional\CommerceWebformOrderTestBase;

/**
 * Tests Commerce Webform Order handler: Order data.
 *
 * @group commerce_webform_order
 */
class CommerceWebformOrderHandlerOrderDataTest extends CommerceWebformOrderTestBase {

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
    'cwo_test_order_data',
  ];

  /**
   * Order data has values to add.
   *
   * In this test we are going to check the order data feature when it is
   * configured to add keys to the order's data field:
   *   - After submit, the order should have two custom keys.
   */
  public function testOrderData() {
    // Test as anonymous user.
    $this->drupalLogout();

    $webform_submission = $this->postProductVariationToWebform('ONE', 'cwo_test_order_data');
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $this->orderItemRepository->getLastByWebformSubmission($webform_submission, 'commerce_webform_order_handler');
    $order = $order_item->getOrder();

    // Check that both keys are present in the order's data.
    $this->assertEquals('Custom order data value 1', $order->getData('custom_order_data_key_1'));
    $this->assertEquals('Custom order data value 2', $order->getData('custom_order_data_key_2'));
  }

}
