<?php

namespace Drupal\Tests\commerce_webform_order\Functional\WebformHandler;

use Drupal\Tests\commerce_webform_order\Functional\CommerceWebformOrderTestBase;

/**
 * Tests Commerce Webform Order handler: Owner.
 *
 * @group commerce_webform_order
 */
class CommerceWebformOrderHandlerOwnerTest extends CommerceWebformOrderTestBase {

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
    'cwo_test_owner_1',
    'cwo_test_owner_2',
  ];

  /**
   * Owner is disabled.
   *
   * In this test we are going to check the owner feature when it is disabled:
   *   - After submit, there isn't any order's owner.
   */
  public function testOwnerDisabled() {
    // Test as anonymous user.
    $this->drupalLogout();

    $webform_submission = $this->postProductVariationToWebform('ONE', 'cwo_test_owner_1');
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $this->orderItemRepository->getLastByWebformSubmission($webform_submission, 'commerce_webform_order_handler');

    // There isn't any order's owner.
    $this->assertEmpty($order_item->getOrder()->getEmail());
  }

  /**
   * Owner is enabled.
   *
   * In this test we are going to check the owner feature when it is enabled:
   *   - After submit, there is an order's owner.
   */
  public function testOwnerEnabled() {
    // Test as anonymous user.
    $this->drupalLogout();

    $webform_submission = $this->postProductVariationToWebform('ONE', 'cwo_test_owner_2');
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $this->orderItemRepository->getLastByWebformSubmission($webform_submission, 'commerce_webform_order_handler');

    // There is any order's owner.
    $this->assertEquals('guest@example.com', $order_item->getOrder()->getEmail());
  }

}
