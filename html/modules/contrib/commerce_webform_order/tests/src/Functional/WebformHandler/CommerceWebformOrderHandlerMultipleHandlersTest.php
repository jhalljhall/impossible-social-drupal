<?php

namespace Drupal\Tests\commerce_webform_order\Functional\WebformHandler;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\commerce_webform_order\Functional\CommerceWebformOrderTestBase;

/**
 * Tests Commerce Webform Order handler: Multiple handlers.
 *
 * @group commerce_webform_order
 */
class CommerceWebformOrderHandlerMultipleHandlersTest extends CommerceWebformOrderTestBase {

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
    'cwo_test_multiple_handlers',
  ];

  /**
   * Submission is completed and order new.
   *
   * In this test we are going to check that first handler configured for new
   * orders and completed submission and a second handler configured for new and
   * draft orders and completed/updated/deleted submission:
   *   - A new order with tow order items is created when making an initial
   *     submission.
   *   - The first order item's handler do not update if we update the
   *     submission.
   *   - The first order item's handler do not add a new order item to the order
   *     if we make a new submit.
   *   - The second handler's order items are removed if we remove the
   *     submission.
   */
  public function testMultipleHandlers() {
    // Test as anonymous user.
    $this->drupalLogout();

    // Test completed submission and new order.
    $values = [
      'product_1' => $this->productVariations['ONE']->id(),
      'product_2' => $this->productVariations['TWO']->id(),
    ];
    $webform_submission_1 = clone ($this->postValuesToWebform($values, 'cwo_test_multiple_handlers'));
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item_1 */
    $order_item_1 = clone ($this->orderItemRepository->getLastByWebformSubmission($webform_submission_1, 'commerce_webform_order_handler_1'));
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item_2 */
    $order_item_2 = clone ($this->orderItemRepository->getLastByWebformSubmission($webform_submission_1, 'commerce_webform_order_handler_2'));

    // Confirm that the order items have been created.
    $this->assertEquals($order_item_1->getOrderId(), $order_item_2->getOrderId());
    $this->assertEquals(2, count($order_item_1->getOrder()->getItems()));
    $this->assertEquals($this->productVariations['ONE']->getSku(), $order_item_1->getPurchasedEntity()->getSku());
    $this->assertEquals($this->productVariations['ONE']->getPrice()->getNumber(), $order_item_1->getTotalPrice()->getNumber());
    $this->assertEquals($this->productVariations['ONE']->getPrice()->add($this->productVariations['TWO']->getPrice())->getNumber(), $order_item_1->getOrder()->getTotalPrice()->getNumber());
    $this->assertEquals(2, count($order_item_2->getOrder()->getItems()));
    $this->assertEquals($this->productVariations['TWO']->getSku(), $order_item_2->getPurchasedEntity()->getSku());
    $this->assertEquals($this->productVariations['TWO']->getPrice()->getNumber(), $order_item_2->getTotalPrice()->getNumber());
    $this->assertEquals($this->productVariations['TWO']->getPrice()->add($this->productVariations['ONE']->getPrice())->getNumber(), $order_item_2->getOrder()->getTotalPrice()->getNumber());

    // Try to update the submission when current user's has an order.
    $this->resetCache();
    $options = ['query' => ['token' => $webform_submission_1->getToken()]];
    $values = [
      'product_1' => $this->productVariations['TWO']->id(),
      'product_2' => $this->productVariations['THREE']->id(),
    ];
    $webform_submission_2 = clone ($this->postValuesToWebform($values, 'cwo_test_multiple_handlers', 'Save', $options));
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item_1 */
    $order_item_3 = clone ($this->orderItemRepository->getLastByWebformSubmission($webform_submission_2, 'commerce_webform_order_handler_1'));
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item_2 */
    $order_item_4 = clone ($this->orderItemRepository->getLastByWebformSubmission($webform_submission_2, 'commerce_webform_order_handler_2'));

    // Confirm that only the second handler has updated the order item.
    $this->assertEquals($webform_submission_1->id(), $webform_submission_2->id());
    $this->assertEquals($order_item_3->getOrderId(), $order_item_4->getOrderId());
    $this->assertEquals(2, count($order_item_3->getOrder()->getItems()));
    $this->assertEquals($this->productVariations['ONE']->getSku(), $order_item_3->getPurchasedEntity()->getSku());
    $this->assertEquals($this->productVariations['ONE']->getPrice()->getNumber(), $order_item_3->getTotalPrice()->getNumber());
    $this->assertEquals($this->productVariations['ONE']->getPrice()->add($this->productVariations['THREE']->getPrice())->getNumber(), $order_item_3->getOrder()->getTotalPrice()->getNumber());
    $this->assertEquals(2, count($order_item_4->getOrder()->getItems()));
    $this->assertEquals($this->productVariations['THREE']->getSku(), $order_item_4->getPurchasedEntity()->getSku());
    $this->assertEquals($this->productVariations['THREE']->getPrice()->getNumber(), $order_item_4->getTotalPrice()->getNumber());
    $this->assertEquals($this->productVariations['THREE']->getPrice()->add($this->productVariations['ONE']->getPrice())->getNumber(), $order_item_4->getOrder()->getTotalPrice()->getNumber());

    // Try to add new order items to an existing order.
    $this->resetCache();
    $values = [
      'product_1' => $this->productVariations['ONE']->id(),
      'product_2' => $this->productVariations['TWO']->id(),
    ];
    $webform_submission_3 = clone ($this->postValuesToWebform($values, 'cwo_test_multiple_handlers'));
    /** @var null $order_item_1 */
    $order_item_5 = $this->orderItemRepository->getLastByWebformSubmission($webform_submission_3, 'commerce_webform_order_handler_1');
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item_2 */
    $order_item_6 = clone ($this->orderItemRepository->getLastByWebformSubmission($webform_submission_3, 'commerce_webform_order_handler_2'));

    // Confirm that only the second handler has added a new order item.
    $this->assertNull($order_item_5);
    $this->assertEquals(3, count($order_item_6->getOrder()->getItems()));
    $this->assertEquals($this->productVariations['TWO']->getSku(), $order_item_6->getPurchasedEntity()->getSku());
    $this->assertEquals($this->productVariations['TWO']->getPrice()->getNumber(), $order_item_6->getTotalPrice()->getNumber());

    // Remove the submissions and confirm that only the first order item exists.
    // The first and second submissions have the same id.
    $this->assertEquals($webform_submission_1->id(), $webform_submission_2->id());
    $webform_submission_2->delete();
    $webform_submission_3->delete();
    $this->resetCache();
    $this->assertEquals(1, count($order_item_1->getOrder()->getItems()));
    $this->assertTrue($order_item_1->getOrder()->hasItem($order_item_1));
  }

}
