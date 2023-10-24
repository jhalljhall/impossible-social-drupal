<?php

namespace Drupal\Tests\webform_product\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the loading of webform product.
 *
 * @group WebformProduct
 */
class WebformProductUiTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Module list.
   *
   * @var array
   */
  protected static $modules = [
    'commerce',
    'commerce_cart',
    'commerce_checkout',
    'commerce_number_pattern',
    'commerce_order',
    'commerce_payment',
    'commerce_payment_example',
    'commerce_price',
    'commerce_product',
    'commerce_store',
    'field',
    'link',
    'user',
    'webform',
    'webform_ui',
    'webform_product',
    'webform_product_test',
  ];

  /**
   * Admin user.
   *
   * @var \Drupal\user\Entity\User|false
   */
  private $adminUser;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setUp(): void {
    parent::setUp();

    // Create the user and login.
    $this->adminUser = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Test webform product handler settings page.
   */
  public function testWebformProductSettingsPage() {
    $this->drupalGet('admin/structure/webform/manage/webform_payment_example/handlers/webform_product/edit');
    $this->assertSession()->fieldValueEquals('edit-settings-order-item-title', '[webform_submission:source-title]');

    $this->assertTrue($this->assertSession()->optionExists('settings[store]', 'Test store')->isSelected());
    $this->assertTrue($this->assertSession()->optionExists('settings[order_type]', 'Default')->isSelected());
    $this->assertTrue($this->assertSession()->optionExists('settings[order_item_type]', 'Webform')->isSelected());
    $this->assertTrue($this->assertSession()->optionExists('settings[checkout_step]', 'review')->isSelected());
    $this->assertTrue($this->assertSession()->optionExists('settings[payment_gateway]', 'example')->isSelected());
    $this->assertTrue($this->assertSession()->optionExists('settings[order_total]', '- None -')->isSelected());
    $this->assertTrue($this->assertSession()->optionExists('settings[payment_status]', 'payment_status')->isSelected());
    $this->assertTrue($this->assertSession()->optionExists('settings[order_id]', 'order_id')->isSelected());
    $this->assertTrue($this->assertSession()->optionExists('settings[order_url]', 'order_url')->isSelected());
    $this->assertTrue($this->assertSession()->optionExists('settings[total_price]', 'total_price')->isSelected());
    $this->assertTrue($this->assertSession()->optionExists('settings[contact_email]', '- None -')->isSelected());

    // Click add another link.
    $this->click('#edit-submit');

    // Ensure we saved correctly.
    $this->assertSession()->pageTextContains('The webform handler was successfully updated.');
  }

  /**
   * Test: Change product price.
   */
  public function testWebformProductChangePrice() {
    $this->drupalGet('admin/structure/webform/manage/webform_payment_example/element/name/edit');
    $this->assertSession()->fieldValueEquals('edit-price', 100);

    $edit = [
      'price' => 50,
    ];
    $this->submitForm($edit, 'Save', 'webform-ui-element-form');

    // Ensure we saved correctly.
    $this->assertSession()->pageTextContains('Name has been updated.');

    $this->drupalGet('admin/structure/webform/manage/webform_payment_example/element/name/edit');
    $this->assertSession()->fieldValueEquals('edit-price', 50);
  }

  /**
   * Test: Order process.
   */
  public function testWebformProductOrderProcess() {
    // Set order default validation:
    $this->drupalGet('admin/commerce/config/order-types/default/edit');
    $edit = [
      'edit-workflow' => 'order_default_validation',
    ];
    $this->submitForm($edit, 'Save', 'commerce-order-type-edit-form');

    // Send webform:
    $this->drupalGet('form/webform-payment-example');

    $edit = [
      'edit-name' => 'Test Elek',
    ];
    $this->submitForm(
      $edit,
      'Submit',
      'webform-submission-webform-payment-example-add-form'
    );

    // Ensure we saved correctly. Check checkout process.
    $this->assertSession()->pageTextContains('webform payment example');
    $this->assertSession()->responseContains('<div class="order-total-line order-total-line__subtotal">
      <span class="order-total-line-label">Subtotal </span><span class="order-total-line-value">€100.00</span>
    </div>');
    $this->assertSession()->responseContains(
      '<div class="order-total-line order-total-line__total">
      <span class="order-total-line-label">Total </span><span class="order-total-line-value">€100.00</span>
    </div>');

    $this->assertSession()->buttonExists('edit-actions-next');
    $this->assertSession()->buttonExists('Pay and complete purchase');

    $checkout_url = $this->getUrl();

    // Check webform submission.
    $this->drupalGet(
      'admin/structure/webform/manage/webform_payment_example/results/submissions'
    );
    $this->assertSession()->pageTextContains('1 (draft)');
    $this->assertSession()->responseContains('<td>1</td>');
    // Failed only drupal.org test bot:
    //    $this->assertSession()->pageTextContains('/admin/commerce/orders/1');
    // Continue checkout:
    $this->drupalGet($checkout_url);
    $this->submitForm(
      [],
      'Pay and complete purchase',
      'commerce-checkout-flow-multistep-default'
    );
    $this->assertSession()->pageTextContains(
      'Please wait while you are redirected to the payment server. If nothing happens within 10 seconds, please click on the button below.'
    );

    $checkout_url = $this->getUrl();

    // Check webform submission.
    $this->drupalGet(
      'admin/structure/webform/manage/webform_payment_example/results/submissions'
    );
    $this->assertSession()->pageTextContains('1 (draft)');
    $this->assertSession()->responseContains('<td>1</td>');
    // Failed only drupal.org test bot:
    //    $this->assertSession()->pageTextContains('/admin/commerce/orders/1');
    // Continue checkout:
    $this->drupalGet($checkout_url);
    $this->submitForm(
      [],
      'Proceed to Example',
      'commerce-checkout-flow-multistep-default'
    );
    $this->assertSession()->pageTextContains('Complete');

    // Validate order:
    $this->drupalGet('/admin/commerce/orders/1');
    $this->assertSession()->pageTextContains('Validation');

    $this->click('#edit-validate');

    $this->assertSession()->pageTextContains('Completed');

    // Check webform submission.
    $this->drupalGet(
      'admin/structure/webform/manage/webform_payment_example/results/submissions'
    );
    $this->assertSession()->pageTextNotContains('1 (draft)');
  }

}
