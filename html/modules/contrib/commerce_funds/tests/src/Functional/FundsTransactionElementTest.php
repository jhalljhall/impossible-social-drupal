<?php

namespace Drupal\Tests\commerce_funds\Functional;

use Drupal\Tests\commerce\Functional\CommerceBrowserTestBase;
use Drupal\Tests\commerce_funds\Traits\FundsTrait;
use Drupal\user\Entity\User;

/**
 * Tests funds transaction element.
 *
 * @group commerce_funds
 */
class FundsTransactionElementTest extends CommerceBrowserTestBase {

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
    'node',
    'commerce_funds',
    'commerce_exchanger',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser($this->getAdministratorPermissions() + [
      'administer commerce_order',
      'administer commerce_payment',
      'access commerce_order overview',
    ], NULL, TRUE);

    $this->firstUser = $this->drupalCreateUser([
      'deposit funds',
      'access checkout',
      'transfer funds',
      'create escrow payment',
      'withdraw funds',
      'convert currencies',
    ]);
    $this->drupalLogin($this->firstUser);
    $this->secondUser = $this->drupalCreateUser();

    $this->paymentGateway = $this->createEntity('commerce_payment_gateway', [
      'id' => 'manual',
      'label' => 'Manual',
      'plugin' => 'manual',
      'collect_billing_information' => FALSE,
      'status' => 1,
    ]);
    $this->transactionManager = \Drupal::service('commerce_funds.transaction_manager');
    $this->productManager = \Drupal::service('commerce_funds.product_manager');
    $this->configFactory = \Drupal::configFactory();
    $this->webAssert = $this->assertSession();
  }

  /**
   * Receive a manual payment.
   *
   * @param float $amount
   *   The amount of the order to be paid.
   * @param string $currency_code
   *   The currency code of the order to be paid.
   */
  protected function validateManualOrder($amount, $currency_code) {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/commerce/orders/1/payments/1/operation/receive');
    $this->submitForm([
      'payment[amount][number]' => $amount,
    ], 'Receive');
  }

  /**
   * Tests Funds deposit field.
   */
  public function testFundsDepositField() {
    $this->createContentAndFundsfield('deposit');
    $this->drupalGet('node/add/deposit');
    $this->webAssert->statusCodeEquals(200);

    // Test with no funds.
    $this->submitForm([
      'title[0][value]' => 'Random title',
      'field_deposit[0][target_id][amount]' => 12.5,
      'field_deposit[0][target_id][currency]' => 'USD',
    ], 'Save');

    // Test checkout redirection.
    $this->assertEquals($this->getAbsoluteUrl('/checkout/1/order_information'), $this->getUrl());
    // Product created + added to order.
    $this->webAssert->pageTextContains('Deposit 12.50 USD');

    // Order information.
    $this->submitForm([
      'payment_information[billing_information][address][0][address][administrative_area]' => 'AL',
      'payment_information[billing_information][address][0][address][given_name]' => 'firstUser',
      'payment_information[billing_information][address][0][address][family_name]' => 'firstUser',
      'payment_information[billing_information][address][0][address][address_line1]' => 'Somewhere',
      'payment_information[billing_information][address][0][address][locality]' => 'Somewhere',
      'payment_information[billing_information][address][0][address][postal_code]' => '35242',
    ], 'Continue to review');

    // Review.
    $this->submitForm([], 'Pay and complete purchase');

    // Funds added to balance.
    $this->webAssert->pageTextContains('12.50 USD have been added to your account balance.');
    // Assert user balance doesn't hold any USD.
    $this->assertArrayNotHasKey('USD', $this->transactionManager->loadAccountBalance($this->firstUser));
    // Receive payment.
    $this->validateManualOrder(12.5, 'USD');
    // Assert deposit is in balance.
    $this->assertEquals(12.5, $this->transactionManager->loadAccountBalance($this->firstUser)['USD']);
  }

  /**
   * Tests Funds transfer and escrow fields.
   */
  public function testFundsTransferEscrowFields() {
    foreach (['transfer', 'escrow'] as $transaction_type) {
      $confirmation_message = $transaction_type == 'transfer' ? 'You have transfered $12.5 USD to' : 'Escrow payment of $12.5 USD successfully created to';
      $this->createContentAndFundsfield($transaction_type);
      $this->drupalGet('node/add/' . $transaction_type);
      $this->webAssert->statusCodeEquals(200);

      // Form values.
      $values = [
        'title[0][value]' => 'Random title',
        'field_' . $transaction_type . '[0][target_id][amount]' => 12.5,
        'field_' . $transaction_type . '[0][target_id][currency]' => 'USD',
        'field_' . $transaction_type . '[0][target_id][username]' => 'secondUser (' . $this->secondUser->id() . ')',
      ];

      // Test with no funds.
      $this->submitForm($values, 'Save');
      $this->webAssert->pageTextContains('Not enough funds to cover this transaction.');

      // Deposit funds.
      $this->depositFunds(25.25);

      // Test transaction is well executed.
      $this->submitForm($values, 'Save');
      $this->webAssert->pageTextContains($confirmation_message . ' ' . $this->secondUser->getAccountName());
      // Assert balance is 25.25 - 12.5.
      $this->assertEquals(12.75, $this->transactionManager->loadAccountBalance($this->firstUser)['USD']);
      // Edit the node to see if transaction field
      // is not editable.
      $this->drupalGet($this->getUrl() . '/edit');
      $this->webAssert->statusCodeEquals(200);
      $this->webAssert->pageTextNotContains('field_' . $transaction_type . '[0][target_id][amount]');

      // Set fees and test transaction.
      $this->configFactory->getEditable('commerce_funds.settings')->set('fees', [$transaction_type . '_rate' => '2'])->save();
      $this->drupalGet('node/add/' . $transaction_type);
      $this->submitForm($values, 'Save');
      $this->webAssert->pageTextContains($confirmation_message . ' ' . $this->secondUser->getAccountName() . ' (fees: $0.25 USD)');
      // Assert user balance is 0.
      $this->assertEquals(0, $this->transactionManager->loadAccountBalance($this->firstUser)['USD']);
      // Assert site balance was updated from $0.25.
      $this->assertEquals(0.25, $this->transactionManager->loadAccountBalance(User::load(1))['USD']);
    }
  }

  /**
   * Tests Funds conversion field.
   */
  public function testFundsConversionField() {
    // Create conversion content type.
    $this->createContentAndFundsfield('conversion');
    $values = [
      'title[0][value]' => 'Random title',
      'field_conversion[0][target_id][currency_left]' => 'USD',
      'field_conversion[0][target_id][amount]' => 10,
      'field_conversion[0][target_id][currency_right]' => 'EUR',
    ];
    // Add another currency.
    $this->createEntity('commerce_currency', [
      'name' => 'Euro',
      'currencyCode' => 'EUR',
      'symbol' => '€',
      'numericCode' => '978',
      'fractionDigits' => 2,
    ]);

    $this->drupalGet('node/add/conversion');
    $this->webAssert->statusCodeEquals(200);

    // Test with no exchange rates.
    $this->submitForm($values, 'Save');
    $this->webAssert->pageTextContains('Operation impossible. No exchange rates found.');

    // Add exchange rates.
    $exchange_rates = $this->createEntity('commerce_exchange_rates', [
      'id' => 'manual',
      'plugin' => 'manual',
    ]);
    $config_name = $exchange_rates->getExchangerConfigName();
    // Set exchange rate rates.
    $this->configFactory->getEditable($config_name)->set('rates', [
      'EUR' => ['USD' => ['value' => 1.20]],
      'USD' => ['EUR' => ['value' => 0.8]],
    ])->save();
    // Set exchange rate plugin to be used.
    $this->configFactory->getEditable('commerce_funds.settings')->set('exchange_rate_provider', 'manual')->save();

    // Test with no funds.
    $this->submitForm($values, 'Save');
    $this->webAssert->pageTextContains('Not enough funds to cover this transaction.');

    // Deposit funds.
    $this->depositFunds(100);

    // Test that transaction is well executed.
    $this->submitForm($values, 'Save');
    $this->webAssert->pageTextContains('$10 USD converted into €8 EUR.');
    $user_balance = $this->transactionManager->loadAccountBalance($this->firstUser);
    // Assert balance udpates.
    $this->assertEquals(90, $user_balance['USD']);
    $this->assertEquals(8, $user_balance['EUR']);
  }

  /**
   * Tests Funds withdrawal request form.
   */
  public function testFundsWithdrawFormSubmission() {
    // Create withdrawal request content type.
    $this->createContentAndFundsfield('withdrawal');
    // Set a withdrawal method.
    $this->configFactory->getEditable('commerce_funds.settings')->set('withdrawal_methods', ['paypal' => 'paypal'])->save();

    // Form values.
    $values = [
      'title[0][value]' => 'Random title',
      'field_withdrawal[0][target_id][amount]' => 12.5,
      'field_withdrawal[0][target_id][currency]' => 'USD',
      'field_withdrawal[0][target_id][methods]' => 'paypal',
    ];

    $this->drupalGet('node/add/withdrawal');
    $this->webAssert->statusCodeEquals(200);

    // Test with no funds.
    $this->submitForm($values, 'Save');
    $this->webAssert->pageTextContains('Not enough funds to cover this transaction.');
    $this->webAssert->pageTextContains('Please enter your details for this withdrawal method first.');

    // Deposit funds.
    $this->depositFunds(100);
    \Drupal::service('user.data')->set('commerce_funds', $this->firstUser->id(), 'paypal', ['paypal_email' => 'firstUser@nomail.com']);

    // Send withdrawal request.
    $this->submitForm($values, 'Save');
    $this->webAssert->pageTextContains('Withdrawal request sent.');
    // Assert balance didn't change.
    $this->assertEquals(100, $this->transactionManager->loadAccountBalance($this->firstUser)['USD']);

    // Set fees.
    $this->configFactory->getEditable('commerce_funds.settings')->set('fees', ['withdraw_paypal_rate' => '2'])->save();
    $this->drupalGet('node/add/withdrawal');
    $this->submitForm($values, 'Save');
    $this->webAssert->pageTextContains('Withdrawal request sent. An extra commission of $0.25 USD will be apllied to your withraw.');
    // Assert balance didn't change.
    $this->assertEquals(100, $this->transactionManager->loadAccountBalance($this->firstUser)['USD']);
  }

}
