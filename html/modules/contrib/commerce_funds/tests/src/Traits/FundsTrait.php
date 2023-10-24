<?php

namespace Drupal\Tests\commerce_funds\Traits;

use Drupal\commerce_funds\Entity\Transaction;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\user\Entity\Role;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;

/**
 * Provides reusable methods for commerce funds tests.
 */
trait FundsTrait {

  use ContentTypeCreationTrait;

  /**
   * Deposit funds for the current user.
   *
   * @param float $amount
   *   The amount to deposit.
   * @param string $currency
   *   The currency code to deposit.
   */
  protected function depositFunds($amount, $currency = 'USD') {
    // Deposit funds.
    $transaction = $this->createEntity('commerce_funds_transaction', [
      'issuer' => $this->firstUser->id(),
      'recipient' => $this->firstUser->id(),
      'type' => 'deposit',
      'method' => $this->paymentGateway->id(),
      'brut_amount' => $amount,
      'net_amount' => $amount,
      'fee' => 0,
      'currency' => $currency,
      'status' => Transaction::TRANSACTION_STATUS['canceled'],
    ]);
    $this->transactionManager->performTransaction($transaction);
    // Assert balance equals deposit.
    $this->assertEquals($amount, $this->transactionManager->loadAccountBalance($transaction->getIssuer())[$currency]);
  }

  /**
   * Create a content type with funds field configured.
   *
   * @param string $type
   *   The field type to be created.
   */
  protected function createContentAndFundsfield($type) {
    $this->drupalCreateContentType([
      'type' => $type,
      'name' => ucfirst($type),
    ]);

    $fieldStorage = FieldStorageConfig::create([
      'field_name' => 'field_' . $type,
      'entity_type' => 'node',
      'type' => 'commerce_funds_transaction',
    ]);
    $fieldStorage->save();

    $field = FieldConfig::create([
      'field_storage' => $fieldStorage,
      'bundle' => $type,
    ]);
    $field->save();

    $form_display = \Drupal::entityTypeManager()
      ->getStorage('entity_form_display')
      ->load('node.' . $type . '.default');

    $form_display->setComponent('field_' . $type, [
      'type' => 'commerce_funds_transaction_' . $type,
    ]);
    $form_display->save();

    // Grant first user permissions to
    // create and edit the content type.
    $roles = $this->firstUser->getRoles();
    $this->grantPermissions(Role::load(reset($roles)), [
      'create ' . $type . ' content',
      'edit own ' . $type . ' content',
    ]);
  }

}
