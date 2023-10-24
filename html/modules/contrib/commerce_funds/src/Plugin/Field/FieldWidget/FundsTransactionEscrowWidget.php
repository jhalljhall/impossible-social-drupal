<?php

namespace Drupal\commerce_funds\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Funds transaction escrow payment field widget.
 *
 * @FieldWidget(
 *   id = "commerce_funds_transaction_escrow",
 *   label = @Translation("Escrow payment"),
 *   field_types = {
 *     "commerce_funds_transaction"
 *   }
 * )
 */
class FundsTransactionEscrowWidget extends FundsTransactionWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['target_id']['#transaction_type'] = 'escrow';

    return $element;
  }

}
