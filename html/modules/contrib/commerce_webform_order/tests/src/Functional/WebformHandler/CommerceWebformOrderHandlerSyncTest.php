<?php

namespace Drupal\Tests\commerce_webform_order\Functional\WebformHandler;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\commerce_webform_order\Functional\CommerceWebformOrderTestBase;

/**
 * Tests Commerce Webform Order handler: Sync.
 *
 * @group commerce_webform_order
 */
class CommerceWebformOrderHandlerSyncTest extends CommerceWebformOrderTestBase {

  use StringTranslationTrait;

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
    'cwo_test_sync_1',
    'cwo_test_sync_2',
  ];

  /**
   * Synchronization is disabled.
   *
   * In this test we are going to check the order item synchronization feature
   * when it is disabled:
   *   - Submission exists after remove the created order item.
   */
  public function testSyncDisabled() {
    // Test as anonymous user.
    $this->drupalLogout();

    $webform_submission = clone ($this->postProductVariationToWebform('ONE', 'cwo_test_sync_1'));
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $this->orderItemRepository->getLastByWebformSubmission($webform_submission, 'commerce_webform_order_handler');

    // Confirm that the submission exists after delete the order item.
    $order_item->delete();
    $this->resetCache();
    $last_sid = $this->getLastSubmissionId($webform_submission->getWebform());
    $this->assertEquals($webform_submission->id(), $last_sid);
  }

  /**
   * Synchronization is enabled.
   *
   * In this test we are going to check the order item synchronization feature
   * when it is enabled:
   *   - Submission doesn't exists after remove the created order item.
   */
  public function testSyncEnabled() {
    // Test as anonymous user.
    $this->drupalLogout();

    $webform_submission = clone ($this->postProductVariationToWebform('ONE', 'cwo_test_sync_2'));
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $this->orderItemRepository->getLastByWebformSubmission($webform_submission, 'commerce_webform_order_handler');

    // Confirm that the submission doesn't exists after delete the order item.
    $order_item->delete();
    $this->resetCache();
    $last_sid = $this->getLastSubmissionId($webform_submission->getWebform());
    $this->assertNotEquals($webform_submission->id(), $last_sid);
  }

}
