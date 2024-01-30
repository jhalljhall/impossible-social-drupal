<?php

namespace Drupal\Tests\commerce_webform_order\Functional\WebformHandler;

use Drupal\Tests\commerce_webform_order\Functional\CommerceWebformOrderTestBase;

/**
 * Tests Commerce Webform Order handler: Purchasable Entity.
 *
 * @group commerce_webform_order
 */
class CommerceWebformOrderHandlerPurchasableEntityTest extends CommerceWebformOrderTestBase {

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
    'cwo_test_purchasable_entity',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->productVariations = [
      'ONE' => $this->createEntity('commerce_purchasable_entity', [
        'type' => 'default',
        'sku' => 'ONE',
        'title' => 'Purchasable Entity #1',
        'price' => [
          'number' => 5.00,
          'currency_code' => 'USD',
        ],
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function postProductVariationToWebform($product_sku, $webform_id, $submit = 'Submit', array $options = []) {
    // Post the selected product variation, load the last submission and return
    // it.
    $values = ['product' => $this->productVariations[$product_sku]->id()];

    return $this->postValuesToWebform($values, $webform_id, $submit, $options);
  }

  /**
   * New cart is disabled.
   */
  public function testPurchasableEntity() {
    $webform_submission = $this->postProductVariationToWebform('ONE', 'cwo_test_purchasable_entity');
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $this->orderItemRepository->getLastByWebformSubmission($webform_submission, 'commerce_webform_order_handler');

    $this->assertEquals('commerce_purchasable_entity', $order_item->getPurchasedEntity()->getEntityTypeId());
  }

}
