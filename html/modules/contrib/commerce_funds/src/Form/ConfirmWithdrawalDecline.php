<?php

namespace Drupal\commerce_funds\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_funds\TransactionManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a confirmation form to decline a withdrawal request.
 */
class ConfirmWithdrawalDecline extends ConfirmFormBase {

  /**
   * The transaction manager.
   *
   * @var \Drupal\commerce_funds\TransactionManagerInterface
   */
  protected $transactionManager;

  /**
   * The transaction.
   *
   * @var \Drupal\commerce_funds\Entity\Transaction
   */
  protected $transaction;

  /**
   * Class constructor.
   */
  public function __construct(TransactionManagerInterface $transaction_manager) {
    $this->transactionManager = $transaction_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_funds.transaction_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() : string {
    return "confirm_withdrawal_decline";
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $request_hash = NULL) {
    // Load the request.
    $this->transaction = \Drupal::service('commerce_funds.transaction_manager')->loadTransactionByHash($request_hash);

    $form['reason'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Reason for decline'),
      '#description' => $this->t('The message will be addressed to the requester by email.'),
      '#default_value' => $this->transaction->getNotes(),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('view.commerce_funds_transactions.withdrawal_requests');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to decline request: %id?', ['%id' => $this->transaction->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $transaction = $this->transaction;
    // Update request.
    $transaction->setStatus($transaction::TRANSACTION_STATUS['declined']);
    $transaction->setNotes($form_state->getValue('reason'));
    $transaction->save();

    // Send an email to the requester.
    $this->transactionManager->sendTransactionMails($transaction);
    // Generate confirmation message.
    $this->transactionManager->generateConfirmationMessage($transaction);

    // Set redirection.
    $form_state->setRedirect('view.commerce_funds_transactions.withdrawal_requests');
  }

}
