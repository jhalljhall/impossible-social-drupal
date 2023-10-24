<?php

namespace Drupal\commerce_funds\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_funds\Entity\Transaction;
use Drupal\commerce_funds\AvailableCurrenciesTrait;

/**
 * Implements a 'funds transactions' field type.
 *
 * @FieldType(
 *   id = "commerce_funds_transaction",
 *   label = @Translation("Transaction"),
 *   description = @Translation("This field stores the ID of a transaction as integer value."),
 *   category = @Translation("Commerce funds"),
 *   default_widget = "commerce_funds_transaction_transfer",
 *   default_formatter = "commerce_funds_transaction",
 *   cardinality = 1,
 *   list_class = "\Drupal\commerce_funds\Plugin\Field\FieldType\FundsTransactionFieldItemList",
 *   constraints = {"ReferenceAccess" = {}}
 * )
 */
class FundsTransactionItem extends EntityReferenceItem {

  use AvailableCurrenciesTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'target_type' => 'commerce_funds_transaction',
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return parent::defaultFieldSettings() + self::defaultCurrencySettings() + [
      'enable_notes' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'target_id' => [
          'description' => 'The ID of the target transaction.',
          'type' => 'int',
          'not null' => TRUE,
          'unsigned' => TRUE,
        ],
      ],
      'indexes' => [
        'target_id' => ['target_id'],
      ],
      'foreign keys' => [
        'target_id' => [
          'table' => 'commerce_funds_transactions',
          'columns' => ['target_id' => 'transaction_id'],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element = $this->currencySettingsForm($form, $form_state);

    $element['enable_notes'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable notes?'),
      '#description' => $this->t("Users will have the possibility to enter notes (only for transfers and escrows)."),
      '#default_value' => $this->getSetting('enable_notes'),
      '#ajax' => [
        'callback' => [get_class($this), 'reloadDefaultWidget'],
        'event' => 'change',
        'wrapper' => 'funds-transaction-wrapper',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Reloading default widget...'),
        ],
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function getPreconfiguredOptions() {
    // Avoid duplicating options in select list.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    // Bypass saving value when default form widget.
    if (!is_array($values['target_id'])) {
      parent::setValue($values, $notify);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTransaction() {
    return isset($this->transaction_id) ? Transaction::load($this->transaction_id) : NULL;
  }

  /**
   * Ajax callback.
   */
  public static function reloadDefaultWidget(&$form, $form_state) {
    return $form['default_value']['widget'];
  }

}
