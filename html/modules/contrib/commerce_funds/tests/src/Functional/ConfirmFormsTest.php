<?php

namespace Drupal\Tests\commerce_funds\Functional;

use Drupal\Tests\commerce\Functional\CommerceBrowserTestBase;
use Drupal\Tests\commerce_funds\Traits\FundsTrait;

/**
 * Tests confirm forms.
 *
 * @group commerce_funds
 */
class ConfirmFormsTest extends CommerceBrowserTestBase {

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
      'create escrow payment',
      'withdraw funds',
      'view own transactions',
    ]);
    $this->secondUser = $this->drupalCreateUser([
      'create escrow payment',
      'view own transactions',
    ]);
    $this->fundsAdmin = $this->drupalCreateUser([
      'view the administration theme',
      'access administration pages',
      'administer funds',
      'administer transactions',
      'administer withdrawal requests',
    ]);
    $this->paymentGateway = $this->createEntity('commerce_payment_gateway', [
      'id' => 'manual',
      'label' => 'Manual',
      'plugin' => 'manual',
      'collect_billing_information' => FALSE,
      'status' => 1,
    ]);

    $this->drupalLogin($this->firstUser);

    $this->transactionManager = \Drupal::service('commerce_funds.transaction_manager');
    $this->productManager = \Drupal::service('commerce_funds.product_manager');
    $this->configFactory = \Drupal::configFactory();
    $this->webAssert = $this->assertSession();
  }

  /**
   * Tests escrow confirm forms.
   */
  public function testEscrowConfirmForms() {
    // Deposit funds.
    $this->depositFunds(100);
    // Send escrow to second user.
    $this->drupalGet('user/funds/escrow');
    $this->webAssert->statusCodeEquals(200);
    $this->submitForm([
      'amount' => 12.5,
      'currency' => 'USD',
      'username' => 'secondUser (' . $this->secondUser->id() . ')',
    ], 'Create escrow');

    // Go to manage escrows.
    $this->drupalGet('user/funds/escrow/manage');
    $this->webAssert->statusCodeEquals(200);

    // Cancel it from issuer.
    $this->clickLink('Cancel');
    $this->webAssert->pageTextContains('Are you sure you want to cancel that escrow payment?');
    $this->submitForm([], 'Confirm');
    $this->webAssert->pageTextContains('Escrow payment of $12.5 to ' . $this->secondUser->getAccountName() . ' has been canceled.');
    // Assert funds were restored.
    $this->assertEquals(100, $this->transactionManager->loadAccountBalance($this->firstUser)['USD']);

    // Create a new escrow.
    $this->drupalGet('user/funds/escrow');
    $this->webAssert->statusCodeEquals(200);
    $this->submitForm([
      'amount' => 12.5,
      'currency' => 'USD',
      'username' => 'secondUser (' . $this->secondUser->id() . ')',
    ], 'Create escrow');

    // Go to manage escrows.
    $this->drupalGet('user/funds/escrow/manage');
    $this->webAssert->statusCodeEquals(200);

    // Release it.
    $this->clickLink('Release');
    $this->webAssert->pageTextContains('Are you sure you want to release that escrow payment?');
    $this->submitForm([], 'Confirm');
    $this->webAssert->pageTextContains('You have transfered $12.5 USD to ' . $this->secondUser->getAccountName() . '.');
    // Assert both user balances.
    $this->assertEquals(87.5, $this->transactionManager->loadAccountBalance($this->firstUser)['USD']);
    $this->assertEquals(12.5, $this->transactionManager->loadAccountBalance($this->secondUser)['USD']);

    // Create a new escrow.
    $this->drupalGet('user/funds/escrow');
    $this->webAssert->statusCodeEquals(200);
    $this->submitForm([
      'amount' => 12.5,
      'currency' => 'USD',
      'username' => 'secondUser (' . $this->secondUser->id() . ')',
    ], 'Create escrow');

    // Log in as secondUser.
    $this->drupalLogin($this->secondUser);
    $this->drupalGet('user/funds/escrow/manage');

    // Cancel it from recipient.
    $this->clickLink('Cancel');
    $this->webAssert->pageTextContains('Are you sure you want to cancel that escrow payment?');
    $this->submitForm([], 'Confirm');
    $this->webAssert->pageTextContains('Escrow payment of $12.5 from ' . $this->firstUser->getAccountName() . ' has been canceled.');
    // Assert both user balances.
    $this->assertEquals(87.5, $this->transactionManager->loadAccountBalance($this->firstUser)['USD']);
    $this->assertEquals(12.5, $this->transactionManager->loadAccountBalance($this->secondUser)['USD']);
  }

  /**
   * Tests withdrawal request confirm forms.
   */
  public function testWithdrawalConfirmForms() {
    // Set a withdrawal method.
    $this->configFactory->getEditable('commerce_funds.settings')->set('withdrawal_methods', ['paypal' => 'paypal'])->save();
    // Add it to firstUser.
    \Drupal::service('user.data')->set('commerce_funds', $this->firstUser->id(), 'paypal', ['paypal_email' => 'firstUser@nomail.com']);
    // Deposit funds.
    $this->depositFunds(100);
    // Submit a withdrawal request.
    $this->drupalGet('user/funds/withdraw');
    $this->webAssert->statusCodeEquals(200);
    $this->submitForm([
      'amount' => 12.5,
      'currency' => 'USD',
      'methods' => 'paypal',
    ], 'Submit request');

    // Log in as admin.
    $this->drupalLogin($this->fundsAdmin);
    $this->drupalGet('admin/commerce/funds/view-withdraw-requests');
    // Approve withdrawal.
    $this->clickLink('Approve');
    $this->webAssert->pageTextContains('Are you sure you want to approve request:');
    $this->submitForm([], 'Confirm');
    // Assert user balance.
    $this->assertEquals(87.5, $this->transactionManager->loadAccountBalance($this->firstUser)['USD']);

    // Redo it with fees.
    $this->configFactory->getEditable('commerce_funds.settings')->set('fees', [
      'withdraw_paypal_rate' => '2',
    ])->save();
    $this->drupalLogin($this->firstUser);
    $this->drupalGet('user/funds/withdraw');
    $this->webAssert->statusCodeEquals(200);
    $this->submitForm([
      'amount' => 12.5,
      'currency' => 'USD',
      'methods' => 'paypal',
    ], 'Submit request');
    $this->drupalLogin($this->fundsAdmin);
    $this->drupalGet('admin/commerce/funds/view-withdraw-requests');
    $this->clickLink('Approve');
    $this->webAssert->pageTextContains('Are you sure you want to approve request:');
    $this->submitForm([], 'Confirm');
    // Assert user balance + site balance.
    $this->assertEquals(74.75, $this->transactionManager->loadAccountBalance($this->firstUser)['USD']);
    $this->assertEquals(0.25, $this->transactionManager->loadSiteBalance()['USD']);
  }

}
