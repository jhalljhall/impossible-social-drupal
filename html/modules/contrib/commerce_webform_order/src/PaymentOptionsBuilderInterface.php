<?php

namespace Drupal\commerce_webform_order;

/**
 * Defines the interface for the payment options builder service.
 */
interface PaymentOptionsBuilderInterface {

  /**
   * Builds the payment options.
   *
   * The payment options will be derived from the given payment gateways
   * in the following order:
   * 1) The current users' stored payment methods.
   * 2) Options to create new payment methods of valid types.
   * 3) Options for the remaining gateways (off-site, manual, etc).
   *
   * @param \Drupal\commerce_payment\Entity\PaymentGatewayInterface[] $payment_gateways
   *   The payment gateways. When empty, defaults to all available gateways.
   *
   * @return \Drupal\commerce_payment\PaymentOption[]
   *   The payment options, keyed by option ID.
   */
  public function buildOptions(array $payment_gateways = []);

}
