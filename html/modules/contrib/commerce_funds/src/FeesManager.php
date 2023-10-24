<?php

namespace Drupal\commerce_funds;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\commerce_payment\PaymentOptionsBuilderInterface;
use Drupal\commerce_price\Calculator;
use Drupal\commerce_price\Entity\Currency;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_exchanger\Entity\ExchangeRates;

/**
 * Fees Manager class.
 */
class FeesManager implements FeesManagerInterface {

  use StringTranslationTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The payment option builder service.
   *
   * @var \Drupal\commerce_payment\PaymentOptionsBuilderInterface
   */
  protected $paymentOptionsBuilder;

  /**
   * The product manager service.
   *
   * @var \Drupal\commerce_funds\ProductManagerInterface
   */
  protected $productManager;

  /**
   * Class constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $current_user, ModuleHandlerInterface $module_handler, PaymentOptionsBuilderInterface $payment_options_builder, ProductManagerInterface $product_manager) {
    $this->config = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->moduleHandler = $module_handler;
    $this->paymentOptionsBuilder = $payment_options_builder;
    $this->productManager = $product_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateOrderFee(Order $order) {
    $fees = $this->config->get('commerce_funds.settings')->get('fees');
    // No need to go further if no fees are set or user has admin permissions.
    if (!isset($fees) || !$fees || $this->currentUser->hasPermission('administer transactions')) {
      return 0;
    }
    $payment_gateway = $order->get('payment_gateway')->getString();
    if (!$payment_gateway) {
      $options = $this->paymentOptionsBuilder->buildOptions($order);
      $payment_gateway = $this->paymentOptionsBuilder->selectDefaultOption($order, $options)->getPaymentGatewayId();
    }

    $fee_rate = $fees['deposit_' . $payment_gateway . '_rate'] ?? 0;
    $fee_fixed = $fees['deposit_' . $payment_gateway . '_fixed'] ?? 0;

    $deposit_amount = (string) $order->getItems()[0]->getTotalPrice()->getNumber();

    $rate = Calculator::divide((string) $fee_rate, '100');
    $deposit_amount_after_fee_rate = Calculator::round(Calculator::multiply($deposit_amount, Calculator::add('1', $rate)), 2);
    $deposit_amount_after_fee_fixed = Calculator::add($deposit_amount, (string) $fee_fixed);
    $deposit_amount_after_fees = max($deposit_amount_after_fee_rate, $deposit_amount_after_fee_fixed);

    $fee = Calculator::subtract($deposit_amount_after_fees, $deposit_amount);

    return $fee;
  }

  /**
   * {@inheritdoc}
   */
  public function applyFeeToOrder(Order $order) {
    // Calculate the fee and kill the function if no fee.
    $fee = $this->calculateOrderFee($order);
    if (!$fee) {
      return $order;
    }

    $currency_code = $order->getItems()[0]->getTotalPrice()->getCurrencyCode();
    $product_variation = $this->productManager->createProduct('fee', $fee, $currency_code);
    $updated_order = $this->productManager->updateOrder($order, $product_variation);

    return $updated_order;
  }

  /**
   * {@inheritdoc}
   */
  public function printPaymentGatewayFees($payment_gateway, $currency_code, $type) {
    $fees = $this->config->get('commerce_funds.settings')->get('fees');
    if (!$fees) {
      return '';
    }

    // Get rate and fixed fees.
    $rate_fee = $fees[$type . '_' . $payment_gateway . '_rate'] ?? 0;
    $fixed_fee = $fess[$type . '_' . $payment_gateway . '_fixed'] ?? 0;

    // Create message.
    $fees_applied = '';
    if ($rate_fee && !$fixed_fee) {
      $fees_applied = $this->t('(Fee = @rate_fee%)', [
        '@rate_fee' => $rate_fee,
      ]);
    }
    elseif ($rate_fee && $fixed_fee) {
      $fees_applied = $this->t('(Fees = @rate_fee% min @fixed_fee @currency)', [
        '@rate_fee' => $rate_fee,
        '@fixed_fee' => $fixed_fee,
        '@currency' => $currency_code,
      ]);
    }
    elseif (!$rate_fee && $fixed_fee) {
      $fees_applied = $this->t('(Fee = @fixed_fee @currency)', [
        '@fixed_fee' => $fixed_fee,
        '@currency' => $currency_code,
      ]);
    }

    return $fees_applied;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateTransactionFee($brut_amount, $currency, $type) {
    $fees = $this->config->get('commerce_funds.settings')->get('fees');
    // No need to go further if no fees are set.
    if (!isset($fees) || !$fees || $this->currentUser->hasPermission('administer transactions')) {
      return [
        'net_amount' => $brut_amount,
        'fee' => '0',
      ];
    }
    $fee_rate = $fees[$type . '_rate'] ?? '0';
    $fee_fixed = $fees[$type . '_fixed'] ?? '0';
    $rate = Calculator::divide((string) $fee_rate, '100');
    $transaction_amount_after_fee_rate = Calculator::round(Calculator::multiply((string) $brut_amount, Calculator::add('1', $rate)), 2);
    $transaction_amount_after_fee_fixed = Calculator::add((string) $brut_amount, (string) $fee_fixed);
    $transaction_amount_after_fees = max($transaction_amount_after_fee_rate, $transaction_amount_after_fee_fixed);

    $fee = Calculator::subtract($transaction_amount_after_fees, (string) $brut_amount);

    if ($type == 'payment') {
      $transaction_amount_after_fees = Calculator::subtract((string) $brut_amount, (string) $fee);
    }

    return [
      'net_amount' => $transaction_amount_after_fees,
      'fee' => $fee,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function printTransactionFees($transaction_type) {
    $fees = $this->config->get('commerce_funds.settings')->get('fees') ?: [];
    $store = $this->entityTypeManager->getStorage('commerce_store')->loadDefault();
    $funds_default_currency = FundsDefaultCurrency::create(\Drupal::getContainer(), $store);
    $currency = $funds_default_currency->printTransactionCurrency();

    $rate_fee = $fees[$transaction_type . '_rate'] ?? 0;
    $fixed_fee = $fees[$transaction_type . '_fixed'] ?? 0;

    if ($rate_fee && !$fixed_fee) {
      $fees_description = $this->t('Extra commission: @rate_fee%.', [
        '@rate_fee' => $rate_fee,
      ]);
    }
    elseif ($rate_fee && $fixed_fee) {
      $fees_description = $this->t('Extra commission: @rate_fee% (min. @fixed_fee @currency).', [
        '@rate_fee' => $rate_fee,
        '@fixed_fee' => $fixed_fee,
        '@currency' => $currency,
      ]);
    }
    elseif (!$rate_fee && $fixed_fee) {
      $fees_description = $this->t('Extra commission: @fixed_fee @currency.', [
        '@fixed_fee' => $fixed_fee,
        '@currency' => $currency,
      ]);
    }
    else {
      $fees_description = $this->t('Amount for the @transaction_name.', [
        '@transaction_name' => str_replace('_', ' ', ucfirst($transaction_type)),
      ]);
    }

    return $fees_description;
  }

  /**
   * {@inheritdoc}
   */
  public function getExchangeRates() {
    // Get exchange rates.
    $exchange_rates = [];
    $exchange_rate_provider = $this->config->get('commerce_funds.settings')->get('exchange_rate_provider');
    $module_exists = $this->moduleHandler->moduleExists('commerce_exchanger');

    if ($module_exists && $exchange_rate_provider && ($exchange_rates = ExchangeRates::load($exchange_rate_provider))) {
      $exchanger_id = $exchange_rates->getExchangerConfigName();
      $exchange_rates = $this->config->get($exchanger_id)->get('rates');
    }

    return $exchange_rates;
  }

  /**
   * {@inheritdoc}
   */
  public function convertCurrencyAmount($amount, $currency_left, $currency_right) {
    $exchange_rates = $this->getExchangeRates();
    $rate = !empty($exchange_rates[$currency_left][$currency_right]) ? $exchange_rates[$currency_left][$currency_right]['value'] : '0';

    $new_amount = Calculator::round(Calculator::multiply($amount, $rate), 2);
    $conversion = [
      'new_amount' => $new_amount,
      'rate' => $rate,
    ];

    return $conversion;
  }

  /**
   * {@inheritdoc}
   */
  public function printConvertedAmount(string $amount, string $currency_left, string $currency_right) {
    if (!$amount || !$currency_left || !$currency_right) {
      return '0';
    }

    $exchange_rates = $this->getExchangeRates();
    $rate = !empty($exchange_rates[$currency_left][$currency_right]) ? $exchange_rates[$currency_left][$currency_right]['value'] : '0';

    $symbol = Currency::load($currency_right)->getSymbol();

    return $symbol . Calculator::round(Calculator::multiply($amount, $rate), 2);
  }

}
