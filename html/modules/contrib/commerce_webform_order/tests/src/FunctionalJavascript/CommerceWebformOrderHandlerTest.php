<?php

namespace Drupal\Tests\commerce_webform_order\FunctionalJavascript;

use Drupal\entity\BundleFieldDefinition;
use Drupal\Tests\commerce\FunctionalJavascript\CommerceWebDriverTestBase;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Tests the Commerce Webform Order handler UI.
 *
 * @group commerce_webform_order
 */
class CommerceWebformOrderHandlerTest extends CommerceWebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_webform_order_test',
    'commerce_payment',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Update the existing store and create another one.
    $this->store->setName('My first store')->save();
    // Create a second store and set it as default.
    $second_store = $this->createStore();
    $second_store->setName('My second store')->save();

    // Import the EUR currency.
    $this->container->get('commerce_price.currency_importer')->import('EUR');

    // Add a custom order item field.
    $this->container->get('commerce.configurable_field_manager')
      ->createField(
        BundleFieldDefinition::create('string')
          ->setTargetEntityTypeId('commerce_order_item')
          ->setTargetBundle('default')
          ->setName('custom_field')
          ->setLabel('Custom field'),
        FALSE
      );

    // Create a purchasable entity.
    $this->createEntity('commerce_purchasable_entity', [
      'type' => 'default',
      'sku' => 'My purchasable entity',
      'price' => [
        'number' => 10,
        'currency_code' => 'USD',
      ],
    ]);

    // Create a product variation.
    $this->createEntity('commerce_product', [
      'type' => 'default',
      'title' => $this->randomMachineName(),
      'stores' => [$this->store],
      'variations' => [
        $this->createEntity('commerce_product_variation', [
          'type' => 'default',
          'sku' => 'My product variation',
          'price' => [
            'number' => 100,
            'currency_code' => 'USD',
          ],
        ]),
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'administer webform',
    ], parent::getAdministratorPermissions());
  }

  /**
   * Tests handler fields.
   */
  public function testHandlerFields() {
    // Add a handler through the add form.
    $this->drupalGet('/admin/structure/webform/manage/cwo_test_handler/handlers');
    $this->getSession()->getPage()->clickLink('Add handler');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->linkExists('Commerce Webform Order Handler');
    $this->getSession()->getPage()->clickLink('Commerce Webform Order Handler');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Ensure that the configuration sections exist.
    $this->assertSession()->linkExists('Store');
    $this->assertSession()->linkExists('Order item');
    $this->assertSession()->linkExists('Checkout');

    // Check store fields.
    $this->getSession()->getPage()->clickLink('Store');
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[store][store_entity][select]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[store][store_entity][other]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[store][bypass_access]'));

    // Check order item fields.
    $this->getSession()->getPage()->clickLink('Order item');
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[order_item][purchasable_entity_type][select]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[order_item][purchasable_entity_type][other]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[order_item][purchasable_entity]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[order_item][title][select]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[order_item][title][other]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[order_item][amount][select]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[order_item][amount][other]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[order_item][currency][select]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[order_item][currency][other]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[order_item][quantity][select]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[order_item][quantity][other]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[order_item][order_item_bundle]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[order_item][fields][default][custom_field][select]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[order_item][fields][default][custom_field][other]'));

    // Check order item fields.
    $this->getSession()->getPage()->clickLink('Order item');
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[checkout][new_cart]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[checkout][empty_cart]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[checkout][combine_cart]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[checkout][owner][select]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[checkout][owner][other]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[checkout][billing_profile_id][select]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[checkout][billing_profile_id][other]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[checkout][billing_profile_bypass_access]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[checkout][payment_gateway_id][select]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[checkout][payment_gateway_id][other]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[checkout][payment_method_id][select]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[checkout][payment_method_id][other]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[checkout][cancel_url][select]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[checkout][cancel_url][other]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[checkout][hide_add_to_cart_message]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[checkout][redirect]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[checkout][order_state][select]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[checkout][order_state][other]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[checkout][order_data]'));

    // Check advanced settings.
    $this->getSession()->getPage()->clickLink('Advanced');
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[sync]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[webform_states][' . WebformSubmissionInterface::STATE_DRAFT_CREATED . ']'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[webform_states][' . WebformSubmissionInterface::STATE_DRAFT_UPDATED . ']'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[webform_states][' . WebformSubmissionInterface::STATE_CONVERTED . ']'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[webform_states][' . WebformSubmissionInterface::STATE_COMPLETED . ']'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[webform_states][' . WebformSubmissionInterface::STATE_UPDATED . ']'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[webform_states][' . WebformSubmissionInterface::STATE_DELETED . ']'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[order_states][]'));
    $this->assertTrue($this->getSession()->getPage()->hasField('settings[debug]'));
  }

}
