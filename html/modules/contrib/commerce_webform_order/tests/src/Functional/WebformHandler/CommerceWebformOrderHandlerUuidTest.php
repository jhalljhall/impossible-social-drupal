<?php

namespace Drupal\Tests\commerce_webform_order\Functional\WebformHandler;

use Drupal\Tests\commerce_webform_order\Functional\CommerceWebformOrderTestBase;

/**
 * Tests Commerce Webform Order handler: Redirect.
 *
 * @group commerce_webform_order
 */
class CommerceWebformOrderHandlerUuidTest extends CommerceWebformOrderTestBase {

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
    'cwo_test_uuid',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->store->set('uuid', '3de5de58-47f3-4392-ab46-75fb12fb721c')
      ->save();
  }

  /**
   * Test store using uuid.
   */
  public function testRedirectDisabled() {
    // Test as anonymous user.
    $this->drupalLogout();

    $webform_submission = $this->postProductVariationToWebform('ONE', 'cwo_test_uuid');

    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $this->orderItemRepository->getLastByWebformSubmission($webform_submission, 'commerce_webform_order_handler');
    $order = $order_item->getOrder();

    $this->assertEquals($this->store->id(), $order->getStoreId());
    $this->assertEquals($this->store->uuid(), $order->getStore()->uuid());
  }

}
