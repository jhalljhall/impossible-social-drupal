<?php

namespace Drupal\Tests\commerce_webform_order\Functional\WebformHandler;

use Drupal\entity\BundleFieldDefinition;
use Drupal\Tests\commerce_webform_order\Functional\CommerceWebformOrderTestBase;

/**
 * Tests Commerce Webform Order handler: Order item.
 *
 * @group commerce_webform_order
 */
class CommerceWebformOrderHandlerOrderItemTest extends CommerceWebformOrderTestBase {

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
    'cwo_test_order_item_1',
    'cwo_test_order_item_2',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Import the EUR currency.
    $this->container->get('commerce_price.currency_importer')->import('EUR');

    // Add a custom order item field.
    $this->container->get('commerce.configurable_field_manager')
      ->createField($this->getOrderItemfield(), FALSE);
  }

  /**
   * Order item ID is stored in an element value.
   */
  public function testCurrentOrderItemId() {
    // Test as anonymous user.
    $this->drupalLogout();

    $webform_submission = $this->postProductVariationToWebform('ONE', 'cwo_test_order_item_1');
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $this->orderItemRepository->getLastByWebformSubmission($webform_submission, 'commerce_webform_order_handler');

    // The order item ID is stored in the configured element.
    $this->assertEquals($order_item->id(), $webform_submission->getElementData('order_item_id'));
  }

  /**
   * Order item fields values.
   */
  public function testCurrentOrderItemFields() {
    // Test as anonymous user.
    $this->drupalLogout();

    $webform_submission = $this->postProductVariationToWebform('TWO', 'cwo_test_order_item_2');
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $this->orderItemRepository->getLastByWebformSubmission($webform_submission, 'commerce_webform_order_handler');

    $this->assertEquals($this->productVariations['TWO']->getSku(), $order_item->getPurchasedEntity()->getSku());
    $this->assertEquals('10', $order_item->getQuantity());
    $this->assertEquals('1000', $order_item->getUnitPrice()->getNumber());
    $this->assertNotEquals($this->productVariations['TWO']->getPrice()->getNumber(), $order_item->getUnitPrice()->getNumber());
    $this->assertEquals('EUR', $order_item->getUnitPrice()->getCurrencyCode());
    $this->assertNotEquals($this->productVariations['TWO']->getPrice()->getCurrencyCode(), $order_item->getUnitPrice()->getCurrencyCode());
  }

  /**
   * Builds the order item custom field definition.
   *
   * @return \Drupal\entity\BundleFieldDefinition
   *   The field definition.
   */
  protected function getOrderItemfield() {
    return BundleFieldDefinition::create('string')
      ->setTargetEntityTypeId('commerce_order_item')
      ->setTargetBundle('default')
      ->setName('custom_field')
      ->setLabel('Custom field');
  }

}
