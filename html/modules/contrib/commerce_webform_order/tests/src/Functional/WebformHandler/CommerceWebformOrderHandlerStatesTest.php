<?php

namespace Drupal\Tests\commerce_webform_order\Functional\WebformHandler;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\commerce_webform_order\Functional\CommerceWebformOrderTestBase;

/**
 * Tests Commerce Webform Order handler: States.
 *
 * @group commerce_webform_order
 */
class CommerceWebformOrderHandlerStatesTest extends CommerceWebformOrderTestBase {

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
    'cwo_test_states_1',
    'cwo_test_states_2',
  ];

  /**
   * Submission is completed and order new.
   *
   * In this test we are going to check that a handler configured for new orders
   * and completed submission:
   *   - A new order is created when making an initial submission.
   *   - The cart is not updated if we update the submission.
   *   - A new order item is not added to the order if we make a new submit.
   */
  public function testStatesCompletedSubmissionAndNewOrder() {
    // Test as anonymous user.
    $this->drupalLogout();

    // Test completed submission and new order.
    $webform_submission = clone ($this->postProductVariationToWebform('ONE', 'cwo_test_states_1'));
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = clone ($this->orderItemRepository->getLastByWebformSubmission($webform_submission, 'commerce_webform_order_handler'));

    // Confirm that the order item has been created.
    $this->assertEquals(1, count($order_item->getOrder()->getItems()));
    $this->assertEquals($this->productVariations['ONE']->getSku(), $order_item->getPurchasedEntity()->getSku());
    $this->assertEquals($this->productVariations['ONE']->getPrice()->getNumber(), $order_item->getTotalPrice()->getNumber());
    $this->assertEquals($this->productVariations['ONE']->getPrice()->getNumber(), $order_item->getOrder()->getTotalPrice()->getNumber());

    // Try to update the submission when current user's has an order.
    $this->resetCache();
    $options = ['query' => ['token' => $webform_submission->getToken()]];
    $webform_submission_2 = clone ($this->postProductVariationToWebform('TWO', 'cwo_test_states_1', 'Save', $options));
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item_2 */
    $order_item_2 = clone ($this->orderItemRepository->getLastByWebformSubmission($webform_submission, 'commerce_webform_order_handler'));

    // Confirm that the order item hasn't been updated.
    $this->assertEquals($webform_submission->id(), $webform_submission_2->id());
    $this->assertEquals(1, count($order_item_2->getOrder()->getItems()));
    $this->assertEquals($order_item->id(), $order_item_2->id());
    $this->assertEquals($order_item->getPurchasedEntity()->getSku(), $order_item_2->getPurchasedEntity()->getSku());
    $this->assertEquals($order_item->getTotalPrice()->getNumber(), $order_item_2->getTotalPrice()->getNumber());
    $this->assertEquals($order_item->getOrder()->getTotalPrice()->getNumber(), $order_item_2->getTotalPrice()->getNumber());

    // Try to add a new order item to an existing order.
    $this->resetCache();
    $webform_submission_3 = clone ($this->postProductVariationToWebform('THREE', 'cwo_test_states_1'));
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item_3 */
    $order_item_3 = $this->orderItemRepository->getLastByWebformSubmission($webform_submission_3, 'commerce_webform_order_handler');

    // Confirm that the order item hasn't been added.
    $this->assertNotEquals($webform_submission->id(), $webform_submission_3->id());
    $this->assertNull($this->orderItemRepository->getLastByWebformSubmission($webform_submission_3, 'commerce_webform_order_handler'));
    $this->assertNull($order_item_3);
  }

  /**
   * Submission is completed, updated or deleted and order is new or draft.
   *
   * In this test we are going to check that a handler configured for new and
   * draft orders and completed/updated/deleted submission:
   *   - A new order is created when making an initial submission.
   *   - The cart is updated if we update the submission.
   *   - A new order item is added to the order if we make a new submit.
   *   - The order item is removed if we remove the submission.
   */
  public function testStatesCompletedDeletedUpdateSubmissionAndNewDraftOrder() {
    // Test as anonymous user.
    $this->drupalLogout();

    // Test completed submission and new order.
    $webform_submission = clone ($this->postProductVariationToWebform('ONE', 'cwo_test_states_2'));
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = clone ($this->orderItemRepository->getLastByWebformSubmission($webform_submission, 'commerce_webform_order_handler'));

    // Confirm that the order item has been created.
    $this->assertEquals(1, count($order_item->getOrder()->getItems()));
    $this->assertEquals($this->productVariations['ONE']->getSku(), $order_item->getPurchasedEntity()->getSku());
    $this->assertEquals($this->productVariations['ONE']->getPrice()->getNumber(), $order_item->getTotalPrice()->getNumber());
    $this->assertEquals($this->productVariations['ONE']->getPrice()->getNumber(), $order_item->getOrder()->getTotalPrice()->getNumber());

    // Try to update the submission when current user's has an order.
    $this->resetCache();
    $options = ['query' => ['token' => $webform_submission->getToken()]];

    $webform_submission_2 = clone ($this->postProductVariationToWebform('TWO', 'cwo_test_states_2', 'Save', $options));
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item_2 */
    $order_item_2 = clone ($this->orderItemRepository->getLastByWebformSubmission($webform_submission_2, 'commerce_webform_order_handler'));

    // Confirm that the order item has been updated.
    $this->assertEquals($webform_submission->id(), $webform_submission_2->id());
    $this->assertEquals(1, count($order_item_2->getOrder()->getItems()));
    $this->assertEquals($order_item->id(), $order_item_2->id());
    $this->assertNotEquals($order_item->getPurchasedEntity()->getSku(), $order_item_2->getPurchasedEntity()->getSku());
    $this->assertEquals($this->productVariations['TWO']->getSku(), $order_item_2->getPurchasedEntity()->getSku());
    $this->assertNotEquals($order_item->getTotalPrice()->getNumber(), $order_item_2->getTotalPrice()->getNumber());
    $this->assertEquals($this->productVariations['TWO']->getPrice()->getNumber(), $order_item_2->getTotalPrice()->getNumber());
    $this->assertEquals($this->productVariations['TWO']->getPrice()->getNumber(), $order_item_2->getOrder()->getTotalPrice()->getNumber());

    // Try to add a new order item to an existing order.
    $this->resetCache();
    $webform_submission_3 = $this->postProductVariationToWebform('THREE', 'cwo_test_states_2');
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item_3 */
    $order_item_3 = $this->orderItemRepository->getLastByWebformSubmission($webform_submission_3, 'commerce_webform_order_handler');

    // Confirm that the new order item has been created and added.
    $this->assertEquals(2, count($order_item_3->getOrder()->getItems()));
    $this->assertEquals($this->productVariations['THREE']->getSku(), $order_item_3->getPurchasedEntity()->getSku());
    $this->assertEquals($this->productVariations['THREE']->getPrice()->getNumber(), $order_item_3->getTotalPrice()->getNumber());
    $this->assertEquals($order_item_3->getOrder()->getTotalPrice()->getNumber(), $order_item_2->getTotalPrice()->getNumber() + $order_item_3->getTotalPrice()->getNumber());

    // Remove the submission and confirm that the last order item has been
    // removed.
    $webform_submission_3->delete();
    $this->resetCache();

    $this->assertFalse($order_item->getOrder()->hasItem($order_item_3));
    $order_items = $order_item->getOrder()->getItems();
    $order_item = end($order_items);
    $this->assertEquals(1, count($order_items));
    $this->assertEquals($this->productVariations['TWO']->getSku(), $order_item->getPurchasedEntity()->getSku());
    $this->assertEquals($this->productVariations['TWO']->getPrice()->getNumber(), $order_item->getTotalPrice()->getNumber());
  }

}
