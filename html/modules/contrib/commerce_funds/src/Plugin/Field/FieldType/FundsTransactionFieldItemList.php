<?php

namespace Drupal\commerce_funds\Plugin\Field\FieldType;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the funds transaction field item list class.
 */
class FundsTransactionFieldItemList extends EntityReferenceFieldItemList {

  /**
   * {@inheritdoc}
   */
  public function defaultValuesFormValidate(array $element, array &$form, FormStateInterface $form_state) {
    // Skip validation on the default value form.
    // This allows setting an empty transaction as the default value.
  }

}
