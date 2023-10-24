<?php

namespace Drupal\commerce_funds\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Url;
use Drupal\commerce_funds\Entity\Transaction;
use Drupal\commerce_funds\TransactionManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a confirmation form to release an escrow payment.
 */
class ConfirmEscrowCancel extends ConfirmFormBase {

  /**
   * The current account.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

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
  public function __construct(AccountProxy $current_user, TransactionManagerInterface $transaction_manager) {
    $this->currentUser = $current_user;
    $this->transactionManager = $transaction_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('commerce_funds.transaction_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() : string {
    return "confirm_escrow_cancel";
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('view.commerce_funds_user_transactions.incoming_escrow_payments');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to cancel that escrow payment?');
  }

  /**
   * Check if the user is allowed to perform an escrow operation.
   *
   * @param \Drupal\commerce_funds\Entity\Transaction $transaction
   *   The transaction id to check permissions on.
   *
   * @return bool
   *   User is allowed or not.
   */
  protected function isUserAllowed(Transaction $transaction) {
    $uid = $this->currentUser->id();

    if ($transaction->getStatus() == $transaction::TRANSACTION_STATUS['pending']) {
      if ($uid == $transaction->getIssuerId() || $uid == $transaction->getRecipientId()) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $transaction_hash = NULL) {
    $transaction = $this->transaction = $this->transactionManager->loadTransactionByHash($transaction_hash);
    // Check if the user is allowed to perform the operation.
    if (!empty($transaction) && $this->isUserAllowed($transaction)) {
      return parent::buildForm($form, $form_state);
    }
    else {
      throw new NotFoundHttpException();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $transaction = $this->transaction;

    // Send emails.
    $this->transactionManager->sendTransactionMails($transaction);
    // Cancel escrow payment.
    $this->transactionManager->addFundsToBalance($transaction, $transaction->getIssuer());
    // Update transaction status.
    $transaction->setStatus($transaction::TRANSACTION_STATUS['canceled']);
    $transaction->save();
    // Generate confirmation message.
    $this->transactionManager->generateConfirmationMessage($transaction);

    // Set redirection.
    $form_state->setRedirect('view.commerce_funds_user_transactions.incoming_escrow_payments');
  }

}
