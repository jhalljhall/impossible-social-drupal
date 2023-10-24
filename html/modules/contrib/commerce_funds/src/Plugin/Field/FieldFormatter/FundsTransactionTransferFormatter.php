<?php

namespace Drupal\commerce_funds\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\Markup;
use Drupal\commerce_funds\Entity\Transaction;

/**
 * Funds transaction field formatter.
 *
 * @FieldFormatter(
 *   id = "commerce_funds_transaction",
 *   label = @Translation("Transaction"),
 *   field_types = {
 *     "commerce_funds_transaction"
 *   }
 * )
 */
class FundsTransactionTransferFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $source = [];
    // Render output using field__funds_transaction theme.
    if ($items->first()) {
      $transaction = Transaction::load($items->first()->getValue()['target_id']);

      $source = [
        '#theme' => 'field__funds_transaction',
        '#issuer' => $transaction->getIssuer()->getAccountName(),
        '#recipient' => $transaction->getRecipient()->getAccountName(),
        '#method' => $transaction->getMethod(),
        '#brut_amount' => $transaction->getBrutAmount(),
        '#net_amount' => $transaction->getNetAmount(),
        '#fee' => $transaction->getFee(),
        '#currency_symbol' => $transaction->getCurrency()->getSymbol(),
        '#currency_code' => $transaction->getCurrencyCode(),
        '#status' => $transaction->getStatus(),
        '#notes' => Markup::create($transaction->getNotes()),
      ];
    }

    $elements[0] = ['#markup' => \Drupal::service('renderer')->render($source)];

    return $elements;
  }

}
