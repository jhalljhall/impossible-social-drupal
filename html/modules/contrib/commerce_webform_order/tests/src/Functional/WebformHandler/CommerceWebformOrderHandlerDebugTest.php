<?php

namespace Drupal\Tests\commerce_webform_order\Functional\WebformHandler;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Tests\commerce_webform_order\Functional\CommerceWebformOrderTestBase;

/**
 * Tests Commerce Webform Order handler: Debug.
 *
 * @group commerce_webform_order
 */
class CommerceWebformOrderHandlerDebugTest extends CommerceWebformOrderTestBase {

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
    'cwo_test_debug_1',
    'cwo_test_debug_2',
    'cwo_test_debug_3',
  ];

  /**
   * Debug is disabled.
   *
   * In this test we are going to check the debug feature when it is disabled:
   *   - After submit, there is't any visible debug string.
   */
  public function testDebugDisabled() {
    // Test as anonymous user.
    $this->drupalLogout();

    $webform_submission = $this->postProductVariationToWebform('ONE', 'cwo_test_debug_1');
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $this->orderItemRepository->getLastByWebformSubmission($webform_submission, 'commerce_webform_order_handler');

    // A status message isn't displayed with the debug message.
    $this->assertSession()->pageTextNotContains(sprintf('Order #%s created.', $order_item->getOrderId()));
    $this->assertSession()->pageTextNotContains(sprintf("Order #%s created to 'guest@example.com'.", $order_item->getOrderId()));
  }

  /**
   * Debug is enabled.
   *
   * In this test we are going to check the debug feature when it is enabled:
   *   - A drupal status message is displayed.
   */
  public function testDebugEnabled() {
    // Test as anonymous user.
    $this->drupalLogout();

    $webform_submission = $this->postProductVariationToWebform('ONE', 'cwo_test_debug_2');
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $this->orderItemRepository->getLastByWebformSubmission($webform_submission, 'commerce_webform_order_handler');

    // A status message is displayed with the debug message.
    $this->assertSession()->pageTextContains(sprintf('Order #%s created.', $order_item->getOrderId()));
    $this->assertSession()->pageTextNotContains(sprintf("Order #%s created to", $order_item->getOrderId()));
    $this->assertSession()->pageTextNotContains("Owner's e-mail");
  }

  /**
   * Debug is enabled and the order has the owner's email.
   *
   * In this test we are going to check the debug feature when it is enabled and
   * the order has the owner's email:
   *   - A drupal status message is displayed with the owner's email.
   */
  public function testDebugEnabledWithOwnersEmail() {
    // Test as anonymous user.
    $this->drupalLogout();

    $webform_submission = $this->postProductVariationToWebform('ONE', 'cwo_test_debug_3');
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $this->orderItemRepository->getLastByWebformSubmission($webform_submission, 'commerce_webform_order_handler');

    // A status message is displayed with the debug message.
    $this->assertSession()->pageTextNotContains(sprintf('Order #%s created.', $order_item->getOrderId()));
    $this->assertSession()->pageTextContains(sprintf("Order #%s created to 'guest@example.com'.", $order_item->getOrderId()));
    $this->assertSession()->pageTextContains("Owner's e-mail guest@example.com");
  }

  /**
   * Assert common debug messages.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  protected function assertDebugMessages(OrderItemInterface $order_item) {
    $this->assertSession()->pageTextContains(sprintf('Store: %s', $this->store->id()));
    $this->assertSession()->pageTextContains(sprintf('Order ID: %s', $order_item->getOrderId()));
    $this->assertSession()->pageTextContains(sprintf('Amount: %s', $order_item->getOrder()->getTotalPrice()->getNumber()));
    $this->assertSession()->pageTextContains(sprintf('Currency: %s', $order_item->getOrder()->getTotalPrice()->getCurrencyCode()));
    $this->assertSession()->pageTextContains(sprintf('Item #%s: %s', $order_item->id(), $order_item->getTitle()));
  }

}
