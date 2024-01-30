<?php

namespace Drupal\Tests\commerce_webform_order\Functional\WebformHandler;

use Drupal\Tests\commerce_webform_order\Functional\CommerceWebformOrderTestBase;

/**
 * Tests Commerce Webform Order handler: Owner.
 *
 * @group commerce_webform_order
 */
class CommerceWebformOrderHandlerOwnerIdTest extends CommerceWebformOrderTestBase {

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
    'cwo_test_owner_id_1',
    'cwo_test_owner_id_2',
  ];

  /**
   * Current user as owner.
   *
   * In this test we are going to check the owner ID feature for current user:
   *   - After submit, there current user is the order's owner.
   */
  public function testCurrentOwnerId() {
    // Test as admin user.
    $webform_submission = $this->postProductVariationToWebform('ONE', 'cwo_test_owner_id_1');
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $this->orderItemRepository->getLastByWebformSubmission($webform_submission, 'commerce_webform_order_handler');
    $order = $order_item->getOrder();

    // Admin user is the order's owner.
    $this->assertEquals($this->adminUser->id(), $order->getCustomerId());
  }

  /**
   * Anonymous as owner.
   *
   * In this test we are going to check the owner ID feature for a specific
   * user:
   *   - After submit, there anonymous user is the order's owner.
   */
  public function testAnonymousOwnerId() {
    // Test as admin user.
    $webform_submission = $this->postProductVariationToWebform('ONE', 'cwo_test_owner_id_2');
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $this->orderItemRepository->getLastByWebformSubmission($webform_submission, 'commerce_webform_order_handler');
    $order = $order_item->getOrder();

    // Anonymous, user is the order's owner.
    $this->assertEquals(0, $order->getCustomerId());
  }

}
