<?php

namespace Drupal\Tests\commerce_webform_order\Functional\WebformHandler;

use Drupal\Tests\commerce_webform_order\Functional\CommerceWebformOrderTestBase;

/**
 * Tests Commerce Webform Order handler: Source Entity.
 *
 * @group commerce_webform_order
 */
class CommerceWebformOrderHandlerSourceEntityTest extends CommerceWebformOrderTestBase {

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
    'cwo_test_source_entity',
  ];

  /**
   * Source entity is added to the submission.
   */
  public function testSourceEntity() {
    // Test as anonymous user.
    $this->drupalLogout();

    $webform_submission = $this->postProductVariationToWebform('ONE', 'cwo_test_source_entity');
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $this->orderItemRepository->getLastByWebformSubmission($webform_submission, 'commerce_webform_order_handler');

    // The source entity of the submission is the order.
    $this->assertEquals($order_item->getOrder()->getEntityTypeId(), $webform_submission->getSourceEntity()->getEntityTypeId());
    $this->assertEquals($order_item->getOrderId(), $webform_submission->getSourceEntity()->id());
  }

}
