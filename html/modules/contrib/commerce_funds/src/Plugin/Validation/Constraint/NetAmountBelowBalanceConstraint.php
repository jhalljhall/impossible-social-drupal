<?php

namespace Drupal\commerce_funds\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * User funds validation constraint.
 *
 * @Constraint(
 *  id = "NetAmountBelowBalance",
 *  label = @Translation("Net amount is superior to balance amount.", context="Validation")
 * )
 */
class NetAmountBelowBalanceConstraint extends Constraint {

  /**
   * {@inheritdoc}
   */
  public $message = "Not enough funds to cover this transfer.";

  /**
   * {@inheritdoc}
   */
  public $messageWithFee = "Not enough funds to cover this transfer (Total: %total (@currency).";

}
