<?php

namespace Drupal\Tests\commerce_funds\Unit\Services;

use Drupal\Tests\UnitTestCase;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\commerce_price\Price;
use Drupal\commerce_payment\PaymentOption;

/**
 * @coversDefaultClass \Drupal\commerce_funds\FeesManager
 *  Using TestFeesManager as class though to override
 *  getExchangeRates() function.
 *
 * @group commerce_funds
 */
class FeesManagerTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  public function setUp() : Void {
    parent::setUp();

    $configFactory = $this->getConfigFactoryStub([
      'commerce_funds.settings' => [
        'fees' => [
          'deposit_manual_rate' => '1',
          'deposit_manual_fixed' => '5',
          'transfer_rate' => '2.5',
          'transfer_fixed' => '5',
          'escrow_rate' => '1.1',
          'escrow_fixed' => '5',
        ],
      ],
    ]);
    $container = new ContainerBuilder();
    $container->set('config.factory', $configFactory);
    \Drupal::setContainer($container);

    $entityTypeManager = $this->getMockBuilder('Drupal\Core\Entity\EntityTypeManagerInterface')
      ->disableOriginalConstructor()
      ->getMock();
    $paymentOptionsBuilder = $this->getMockBuilder('Drupal\commerce_payment\PaymentOptionsBuilderInterface')
      ->disableOriginalConstructor()
      ->getMock();
    $options = new PaymentOption([
      'id' => 'manual',
      'label' => 'Manual',
      'payment_gateway_id' => 'manual',
    ]);
    $paymentOptionsBuilder->method('buildOptions')
      ->willReturn((array) $options);
    $paymentOptionsBuilder->method('selectDefaultOption')
      ->willReturn($options);

    $productManager = $this->getMockBuilder('Drupal\commerce_funds\ProductManager')
      ->disableOriginalConstructor()
      ->getMock();

    $account = $this->getMockBuilder('Drupal\Core\Session\AccountProxyInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $moduleHandler = $this->getMockBuilder('Drupal\Core\Extension\ModuleHandlerInterface')
      ->disableOriginalConstructor()
      ->getMock();

    // We use TestFeesManager here to override
    // getExchangeRates function so we can return
    // predictable values.
    $this->feesManager = new TestFeesManager($configFactory, $entityTypeManager, $account, $moduleHandler, $paymentOptionsBuilder, $productManager);
  }

  /**
   * Covers calculateOrderFee.
   */
  public function testCalculateOrderFee() {
    // Expected values for rates and fixed.
    $amounts = [
      'rates' => [
        'value' => 600,
        'fee' => 6,
      ],
      'fixed' => [
        'value' => 100,
        'fee' => 5,
      ],
    ];

    // For rates fees > fixed fees,
    // and rates fees < fixed fees.
    foreach ($amounts as $amount) {
      $payment_gateway = $this->prophesize(EntityReferenceFieldItemList::class);
      $payment_gateway->getString()->willReturn('manual');
      $payment_gateway = $payment_gateway->reveal();
      $item = $this->prophesize(OrderItemInterface::class);
      $item->getTotalPrice()->willReturn(new Price($amount['value'], 'USD'));
      $item = $item->reveal();
      $order = $this->prophesize(Order::class);
      $order->get('payment_gateway')->willReturn($payment_gateway);
      $order->getItems()->willReturn([$item]);
      $order = $order->reveal();
      $this->assertEquals($amount['fee'], $this->feesManager->calculateOrderFee($order));
    }

  }

  /**
   * Covers calculateOrderFee.
   */
  public function testCalculateTransactionFee() {
    $currency_code = 'USD';
    $existing_types = [
      'transfer' => [
        'rates' => [
          'value' => 444,
          'fee' => 11.1,
        ],
        'fixed' => [
          'value' => 100,
          'fee' => 5,
        ],
      ],
      'escrow' => [
        'rates' => [
          'value' => 555,
          'fee' => 6.11,
        ],
        'fixed' => [
          'value' => 100,
          'fee' => 5,
        ],
      ],
    ];

    foreach ($existing_types as $type => $amounts) {
      foreach ($amounts as $amount) {
        $this->assertEquals($amount['fee'] + $amount['value'], $this->feesManager->calculateTransactionFee($amount['value'], $currency_code, $type)['net_amount']);
        $this->assertEquals($amount['fee'], $this->feesManager->calculateTransactionFee($amount['value'], $currency_code, $type)['fee']);
      }
    }

    // No fee set.
    $non_existing_types = ['withdrawal_request', 'payment', 'conversion'];
    foreach ($non_existing_types as $type) {
      $this->assertEquals(10, $this->feesManager->calculateTransactionFee(10, $currency_code, $type)['net_amount']);
      $this->assertEquals(0, $this->feesManager->calculateTransactionFee(10, $currency_code, $type)['fee']);
    }
  }

  /**
   * Covers convertCurrencyAmount.
   */
  public function testConvertCurrencyAmount() {
    $this->assertEquals(120, $this->feesManager->convertCurrencyAmount(100, 'USD', 'EUR')['new_amount']);
    $this->assertEquals('1.2', $this->feesManager->convertCurrencyAmount('1.2', 'USD', 'EUR')['rate']);
    $this->assertEquals(80, $this->feesManager->convertCurrencyAmount(100, 'EUR', 'USD')['new_amount']);
    $this->assertEquals('0.8', $this->feesManager->convertCurrencyAmount('0.8', 'EUR', 'USD')['rate']);
  }

}
