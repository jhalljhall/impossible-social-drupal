<?php

namespace Drupal\Tests\commerce_funds\Functional;

use Drupal\Tests\commerce_product\Functional\ProductBrowserTestBase;
use Drupal\Tests\commerce_funds\Traits\FundsTrait;

/**
 * Tests the deposit workflow.
 *
 * @group commerce_funds
 */
class PaymentWorkflowTest extends ProductBrowserTestBase {

  use FundsTrait;

  /**
   * Set default theme.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'commerce_funds',
    'commerce_exchanger',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->firstUser = $this->drupalCreateUser([
      'deposit funds',
      'view commerce_product',
      'access checkout',
    ]);

    $this->drupalLogin($this->firstUser);

    $this->paymentGateway = $this->createEntity('commerce_payment_gateway', [
      'id' => 'manual',
      'label' => 'Manual',
      'plugin' => 'manual',
      'collect_billing_information' => FALSE,
      'status' => 1,
    ]);

    $this->createEntity('commerce_payment_gateway', [
      'id' => 'funds',
      'label' => 'Funds',
      'plugin' => 'funds_balance',
      'configuration' => [
        'mode' => 'live',
        'collect_billing_information' => FALSE,
        'payment_method_types' => [
          'funds_wallet',
        ],
      ],
    ]);

    $this->createEntity('commerce_currency', [
      'name' => 'Euro',
      'currencyCode' => 'EUR',
      'symbol' => '€',
      'numericCode' => '978',
      'fractionDigits' => 2,
    ]);

    $currencies = ['USD', 'EUR'];
    foreach ($currencies as $currency) {
      /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $variation */
      $variation = $this->createEntity('commerce_product_variation', [
        'uid' => 1,
        'type' => 'default',
        'sku' => strtolower($this->randomMachineName()),
        'title' => 'Product variation ' . $currency,
        'price' => [
          'number' => 12.5,
          'currency_code' => $currency,
        ],
      ]);

      /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
      $this->createEntity('commerce_product', [
        'uid' => 1,
        'stores' => $this->stores,
        'type' => 'default',
        'title' => 'Product ' . $currency,
        'variations' => [$variation],
      ]);
    }

    $this->transactionManager = \Drupal::service('commerce_funds.transaction_manager');
    $this->productManager = \Drupal::service('commerce_funds.product_manager');
    $this->webAssert = $this->assertSession();
  }

  /**
   * Tests deposit form.
   */
  public function testPaymentWorkflow() {
    // Payment USD.
    // Go to the product page and add it to cart.
    $this->drupalGet('product/1');
    $this->webAssert->statusCodeEquals(200);
    $this->submitForm([], 'Add to cart');

    // Go to cart.
    $this->drupalGet('cart');
    $this->webAssert->statusCodeEquals(200);
    $this->webAssert->pageTextContains('Product USD');
    $this->webAssert->pageTextContains('$12.5');
    $this->submitForm([], 'Checkout');

    // Redirected to order information.
    $this->webAssert->addressEquals('checkout/1/order_information');
    $this->webAssert->pageTextContains("You don't have funds in this currency in your balance.");

    // Creating USD wallet.
    $this->depositFunds(12.5);
    $this->drupalGet('checkout/1/order_information');
    $this->webAssert->pageTextContains('Funds balance (create a new wallet)');
    $this->submitForm([
      'payment_information[payment_method]' => 'new--funds_wallet--funds',
    ], 'Continue to review');

    // Redirected to order review.
    $this->webAssert->addressEquals('checkout/1/review');
    $this->webAssert->pageTextContains('Wallet (USD)');

    // Try to recreate a wallet with existing one.
    $this->drupalGet('checkout/1/order_information');
    $this->submitForm([
      'payment_information[payment_method]' => 'new--funds_wallet--funds',
    ], 'Continue to review');
    $this->webAssert->pageTextContains('You already have a virtual wallet for this currency.');

    // Submit form with funds and USD wallet.
    $this->submitForm([
      'payment_information[payment_method]' => '1',
    ], 'Continue to review');
    $this->submitForm([], 'Pay and complete purchase');
    $this->webAssert->pageTextContains('Your order number is');

    // Assert balance has been updated.
    $this->assertEquals(0, $this->transactionManager->loadAccountBalance($this->firstUser)['USD']);

    // Payment EUR.
    // Go to the product page and add it to cart.
    $this->drupalGet('product/2');
    $this->webAssert->statusCodeEquals(200);
    $this->submitForm([], 'Add to cart');

    // Go to cart.
    $this->drupalGet('cart');
    $this->webAssert->statusCodeEquals(200);
    $this->webAssert->pageTextContains('Product EUR');
    $this->webAssert->pageTextContains('€12.5');
    $this->submitForm([], 'Checkout');

    // Redirected to order information.
    $this->webAssert->addressEquals('checkout/2/order_information');
    // Selecting USD wallet.
    $this->submitForm([
      'payment_information[payment_method]' => '1',
    ], 'Continue to review');
    $this->webAssert->pageTextContains('Wallet with a different currency chosen, please select a EUR wallet.');
    // Creating EUR wallet.
    $this->submitForm([
      'payment_information[payment_method]' => 'new--funds_wallet--funds',
    ], 'Continue to review');
    $this->submitForm([], 'Pay and complete purchase');
    $this->webAssert->pageTextContains("Not enough EUR to pay this order, please make a deposit first.");

    // Selecting EUR wallet with enough funds.
    $this->depositFunds(12.5, 'EUR');
    $this->drupalGet('checkout/2/order_information');
    $this->webAssert->pageTextContains('Wallet (EUR)');
    $this->submitForm([
      'payment_information[payment_method]' => '2',
    ], 'Continue to review');

    $this->submitForm([], 'Pay and complete purchase');
    $this->webAssert->pageTextContains('Your order number is');

    // Assert balance has been updated.
    $this->assertEquals(0, $this->transactionManager->loadAccountBalance($this->firstUser)['EUR']);
  }

}
