<?php

namespace Drupal\commerce_funds\Plugin\views\field;

use Drupal\views\ResultRow;

/**
 * Field handler to provide amount.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("commerce_funds_balance")
 */
class FundsBalance extends MoneyAmount {

  /**
   * {@inheritdoc}
   */
  public function preRender(&$values) {
    // We don't want to run parent preRender.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $all_funds = unserialize($this->getValue($values));
    // There is a transaction.
    if (isset($values->_entity)) {
      $currency_code = $values->_entity->getCurrency()->getCurrencyCode();
      $currency_balance = $all_funds[$currency_code];
      $currency_symbol = $values->_entity->getCurrency()->getSymbol();

      return $currency_symbol . $currency_balance;
    }
    // All balances displayed.
    else {
      foreach ($all_funds as $currency_code => $currency_balance) {
        $balances[] = $currency_code . ' ' . $currency_balance;
      }

      return implode(', ', $balances);
    }
  }

}
