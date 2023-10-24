<?php

namespace Drupal\commerce_funds\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the NetAmountBelowBalance Constraint.
 *
 * @package Drupal\commerce_funds\Plugin\Validation\Constraint
 */
class NetAmountBelowBalanceConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    $amount = $items->getValue()[0]['number'];
    $currency = $items->getValue()[0]['currency_code'];
    $issuer = \Drupal::currentUser();

    $fee_applied = \Drupal::service('commerce_funds.fees_manager')->calculateTransactionFee($amount, $currency, 'transfer');
    $issuer_balance = \Drupal::service('commerce_funds.transaction_manager')->loadAccountBalance($issuer->getAccount(), $currency);
    $currency_balance = $issuer_balance[$currency] ?? 0;
    // Error if the user doesn't have enough money to cover the transfer + fee.
    if ($currency_balance < $fee_applied['net_amount']) {
      if (!$fee_applied['fee']) {
        $this->context->addViolation($constraint->message);
      }
      if ($fee_applied['fee']) {
        $this->context->addViolation($constraint->messageWithFee, [
          '%total' => $fee_applied['net_amount'],
          '@currency' => $currency,
        ]);
      }
    }
  }

}
