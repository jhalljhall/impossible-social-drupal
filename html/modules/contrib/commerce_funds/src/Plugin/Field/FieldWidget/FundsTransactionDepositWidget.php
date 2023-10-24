<?php

namespace Drupal\commerce_funds\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Funds transaction deposit field widget.
 *
 * @FieldWidget(
 *   id = "commerce_funds_transaction_deposit",
 *   label = @Translation("Deposit"),
 *   field_types = {
 *     "commerce_funds_transaction"
 *   }
 * )
 */
class FundsTransactionDepositWidget extends FundsTransactionWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['target_id']['#transaction_type'] = 'deposit';

    return $element;
  }

}
