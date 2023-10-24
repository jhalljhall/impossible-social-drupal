<?php

namespace Drupal\Tests\commerce_funds\Functional;

use Drupal\Tests\commerce\Functional\CommerceBrowserTestBase;
use Drupal\Tests\commerce_funds\Traits\FundsTrait;
use Drupal\user\Entity\User;

/**
 * Tests transaction default forms.
 *
 * @group commerce_funds
 */
class DefaultFormsTest extends CommerceBrowserTestBase {

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
   * Tests Funds deposit form.
   */
  public function testFundsDepositFormSubmission() {
    $this->drupalGet('user/funds/deposit');
    $this->webAssert->statusCodeEquals(200);

    $this->submitForm([
      'amount' => 12.5,
      'currency' => 'USD',
    ], 'Next');

    // Test checkout redirection.
    $this->assertEquals($this->getAbsoluteUrl('/checkout/1/order_information'), $this->getUrl());
    // Product created + added to order.
    $this->webAssert->pageTextContains('Deposit 12.5 USD');

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
   * Tests Funds transfer and escrow forms.
   */
  public function testFundsTransferEscrowFormSubmission() {
    $forms = [
      'transfer' => 'Transfer funds',
      'escrow' => 'Create escrow',
    ];

    foreach ($forms as $transaction_type => $button) {
      $confirmation_message = $transaction_type == 'transfer' ? 'You have transfered $12.5 USD to' : 'Escrow payment of $12.5 USD successfully created to';
      $this->drupalGet('user/funds/' . $transaction_type);
      $this->webAssert->statusCodeEquals(200);

      // Form values.
      $values = [
        'amount' => 12.5,
        'currency' => 'USD',
        'username' => 'secondUser (' . $this->secondUser->id() . ')',
      ];

      // Test with no funds.
      $this->submitForm($values, $button);
      $this->webAssert->pageTextContains('Not enough funds to cover this ' . $transaction_type);

      // Deposit funds.
      $this->depositFunds(25.25);

      // Test transaction is well executed.
      $this->submitForm($values, $button);
      $this->webAssert->pageTextContains($confirmation_message . ' ' . $this->secondUser->getAccountName());
      // Assert balance is 25.25 - 12.5.
      $this->assertEquals(12.75, $this->transactionManager->loadAccountBalance($this->firstUser)['USD']);

      // Set fees.
      $this->configFactory->getEditable('commerce_funds.settings')->set('fees', [$transaction_type . '_rate' => '2'])->save();
      $this->submitForm($values, $button);
      $this->webAssert->pageTextContains($confirmation_message . ' ' . $this->secondUser->getAccountName() . ' (fees: $0.25 USD)');
      // Assert user balance is 0.
      $this->assertEquals(0, $this->transactionManager->loadAccountBalance($this->firstUser)['USD']);
      // Assert site balance was updated from $0.25.
      $this->assertEquals(0.25, $this->transactionManager->loadAccountBalance(User::load(1))['USD']);
      // Assert second user balance.
      if ($transaction_type == 'transfer') {
        $this->assertEquals(25, $this->transactionManager->loadAccountBalance($this->secondUser)['USD']);
      }
    }
  }

  /**
   * Tests Funds converter form.
   */
  public function testFundsConverterFormSubmission() {
    // Form values.
    $values = [
      'currency_left' => 'USD',
      'amount' => 10,
      'currency_right' => 'EUR',
    ];
    // Add another currency.
    $this->createEntity('commerce_currency', [
      'name' => 'Euro',
      'currencyCode' => 'EUR',
      'symbol' => '€',
      'numericCode' => '978',
      'fractionDigits' => 2,
    ]);
    // Add another currency.
    $this->createEntity('commerce_currency', [
      'name' => 'Kuna',
      'currencyCode' => 'HRK',
      'symbol' => 'HRK',
      'numericCode' => '191',
      'fractionDigits' => 2,
    ]);

    // Test with no rates.
    $this->drupalGet('user/funds/converter');
    $this->webAssert->statusCodeEquals(200);
    $this->webAssert->pageTextContains('Sorry, no exchange rates are available at the moment.');

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

    $this->drupalGet('user/funds/converter');

    // Try non set exchange rates.
    $this->submitForm([
      'currency_left' => 'USD',
      'amount' => 10,
      'currency_right' => 'HRK',
    ], 'Convert funds');
    $this->webAssert->pageTextContains('Operation impossible. No exchange rates found.');

    // Deposit funds.
    $this->depositFunds(100);

    // Test that transaction is well executed.
    $this->submitForm($values, 'Convert funds');
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
    // Set a withdrawal method.
    $this->configFactory->getEditable('commerce_funds.settings')->set('withdrawal_methods', ['paypal' => 'paypal'])->save();

    $this->drupalGet('user/funds/withdraw');
    $this->webAssert->statusCodeEquals(200);

    // Form values.
    $values = [
      'amount' => 12.5,
      'currency' => 'USD',
      'methods' => 'paypal',
    ];

    // Test with no funds.
    $this->submitForm($values, 'Submit request');
    $this->webAssert->pageTextContains('Your available balance is 0 USD.');
    $this->webAssert->pageTextContains('Please enter your details for this withdrawal method first.');

    // Deposit funds.
    $this->depositFunds(100);
    \Drupal::service('user.data')->set('commerce_funds', $this->firstUser->id(), 'paypal', ['paypal_email' => 'firstUser@nomail.com']);

    // Send withdrawal request.
    $this->submitForm($values, 'Submit request');
    $this->webAssert->pageTextContains('Withdrawal request sent.');
    // Assert balance didn't change.
    $this->assertEquals(100, $this->transactionManager->loadAccountBalance($this->firstUser)['USD']);

    // Set fees.
    $this->configFactory->getEditable('commerce_funds.settings')->set('fees', ['withdraw_paypal_rate' => '2'])->save();
    $this->submitForm($values, 'Submit request');
    $this->webAssert->pageTextContains('Withdrawal request sent. An extra commission of $0.25 USD will be apllied to your withraw.');
    // Assert balance didn't change.
    $this->assertEquals(100, $this->transactionManager->loadAccountBalance($this->firstUser)['USD']);
  }

}
