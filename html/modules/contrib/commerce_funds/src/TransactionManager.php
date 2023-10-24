<?php

namespace Drupal\commerce_funds;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Utility\Token;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Calculator;
use Drupal\commerce_funds\Entity\Transaction;
use Drupal\commerce_funds\Entity\TransactionInterface;
use Drupal\commerce_funds\Exception\TransactionException;

/**
 * Transaction manager class.
 */
class TransactionManager implements TransactionManagerInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The db connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * AccountProxy definition.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The token utility.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Class constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $connection, AccountProxyInterface $current_user, ConfigFactoryInterface $config_factory, MessengerInterface $messenger, MailManagerInterface $mail_manager, Token $token) {
    $this->entityTypeManager = $entity_type_manager;
    $this->connection = $connection;
    $this->currentUser = $current_user;
    $this->config = $config_factory;
    $this->messenger = $messenger;
    $this->mailManager = $mail_manager;
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public function addDepositToBalance(OrderInterface $order) {
    $deposit_amount = $order->getItems()[0]->getTotalPrice()->getNumber();
    $deposit_currency_code = $order->getItems()[0]->getTotalPrice()->getCurrencyCode();
    $total_paid = $order->getTotalPrice()->getNumber();
    $fee_applied = Calculator::subtract($total_paid, $deposit_amount, 2);
    $payment_method = $order->get('payment_gateway')->getValue()[0]['target_id'];
    $notes = $this->t('Deposit of @amount @currency (order <a href="/user/@user/orders/@order">#@order</a>)', [
      '@amount' => number_format($deposit_amount, 2, '.', ','),
      '@currency' => $deposit_currency_code,
      '@user' => $order->getCustomerId(),
      '@order' => $order->id(),
    ]);

    // Transaction has been created at field level.
    /** @var \Drupal\commerce_funds\Entity\Transaction $transaction */
    if ($order->get('field_transaction')->getString()) {
      $field_values = $order->get('field_transaction')->referencedEntities();
      $transaction = reset($field_values);
      $transaction->setNotes([
        'value' => $notes,
        'format' => 'basic_html',
      ]);
      $transaction->save();
      // Update account balance.
      $this->performTransaction($transaction);
    }
    else {
      // Defines transaction and save it to db.
      $transaction = Transaction::create([
        'issuer' => $order->getCustomerId(),
        'recipient' => $order->getCustomerId(),
        'type' => 'deposit',
        'method' => $payment_method,
        'brut_amount' => $deposit_amount,
        'net_amount' => $total_paid,
        'fee' => $fee_applied,
        'currency' => $deposit_currency_code,
        'status' => Transaction::TRANSACTION_STATUS['canceled'],
        'notes' => [
          'value' => $notes,
          'format' => 'basic_html',
        ],
      ]);
      $transaction->save();
      // Update account balance.
      $this->performTransaction($transaction);
      // Add transaction to order.
      $order->set('field_transaction', $transaction->id());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function performTransaction(TransactionInterface $transaction) {
    $type = $transaction->bundle();
    $issuer = $transaction->getIssuer();
    // Make sure not to perform already completed
    // transactions.
    if ($transaction->getStatus() == Transaction::TRANSACTION_STATUS['completed']) {
      throw new TransactionException(sprintf("Transaction already performed, id: %d", $transaction->id()));
    }
    // Executed on payment success.
    if ($type == 'deposit' && $transaction->access('create', $issuer)) {
      $this->addFundsToBalance($transaction, $issuer);
      $this->updateSiteBalance($transaction);
      $transaction->setStatus(Transaction::TRANSACTION_STATUS['completed']);
    }
    // Executed on transaction creation.
    elseif ($type == 'transfer' && $transaction->access('create', $issuer) || $type == 'payment' && $transaction->access('create', $issuer)) {
      $this->addFundsToBalance($transaction, $transaction->getRecipient());
      $this->removeFundsFromBalance($transaction, $issuer);
      $this->updateSiteBalance($transaction);
      $transaction->setStatus(Transaction::TRANSACTION_STATUS['completed']);
    }
    // Executed on escrow creation, released in confirm form.
    elseif ($type == 'escrow' && $transaction->access('create', $issuer)) {
      $this->removeFundsFromBalance($transaction, $issuer);
      $transaction->setStatus(Transaction::TRANSACTION_STATUS['pending']);
    }
    // Executed on withdrawal approval.
    elseif ($type == 'withdrawal_request' && $transaction->access('create', $this->currentUser)) {
      $this->removeFundsFromBalance($transaction, $issuer);
      $this->updateSiteBalance($transaction);
      $transaction->setStatus(Transaction::TRANSACTION_STATUS['completed']);
    }
    // Executed on transaction creation.
    elseif ($type == 'conversion' && $transaction->access('create', $issuer)) {
      $this->removeFundsFromBalance($transaction, $issuer);
      $this->addFundsToBalance($transaction, $transaction->getRecipient());
      $transaction->setStatus(Transaction::TRANSACTION_STATUS['completed']);
    }
    // Access denied.
    else {
      throw new TransactionException(sprintf("Transaction permission denied: %s", $transaction->bundle()));
    }

    // Save transaction status.
    $transaction->save();
  }

  /**
   * {@inheritdoc}
   */
  public function addFundsToBalance(TransactionInterface $transaction, AccountInterface $account) {
    $brut_amount = $transaction->getBrutAmount();
    $currency_code = $transaction->getCurrencyCode();

    // Cover case where it's an escrow canceled.
    if ($transaction->bundle() == "escrow" && $account->id() == $transaction->getIssuerId()) {
      $brut_amount = $transaction->getNetAmount();
    }

    // Cover conversions.
    if ($transaction->bundle() == "conversion" || $transaction->bundle() == "payment") {
      $brut_amount = $transaction->getNetAmount();
    }

    $balance = $this->loadAccountBalance($account);
    $balance[$currency_code] = $balance[$currency_code] ?? 0;
    $balance[$currency_code] = Calculator::add((string) $brut_amount, (string) $balance[$currency_code], 2);

    // Update the user balance.
    $uid = $account->hasPermission('administer transactions') ? 1 : $account->id();
    $this->connection->merge('commerce_funds_user_funds')
      ->insertFields([
        'uid' => $uid,
        'balance' => serialize($balance),
      ])
      ->updateFields([
        'balance' => serialize($balance),
      ])
      ->key(['uid' => $uid])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function removeFundsFromBalance(TransactionInterface $transaction, AccountInterface $account) {
    $net_amount = $transaction->getNetAmount();
    $currency_code = $transaction->getCurrencyCode();

    if ($transaction->bundle() == 'conversion') {
      $currency_code = $transaction->getFromCurrencyCode();
      $net_amount = $transaction->getBrutAmount();
    }

    if ($transaction->bundle() == 'payment') {
      $net_amount = $transaction->getBrutAmount();
    }

    $balance = $this->loadAccountBalance($account);
    $balance[$currency_code] = $balance[$currency_code] ?? 0;
    $balance[$currency_code] = Calculator::subtract((string) $balance[$currency_code], (string) $net_amount, 2);

    // Update the user balance.
    $uid = $account->hasPermission('administer transactions') ? 1 : $account->id();
    $this->connection->merge('commerce_funds_user_funds')
      ->insertFields([
        'uid' => $uid,
        'balance' => serialize($balance),
      ])
      ->updateFields([
        'balance' => serialize($balance),
      ])
      ->key(['uid' => $uid])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function updateSiteBalance(TransactionInterface $transaction) {
    $currency_code = $transaction->getCurrencyCode();
    $site_balance = $this->loadSiteBalance();

    $site_balance[$currency_code] = $site_balance[$currency_code] ?? 0;
    $site_balance[$currency_code] = Calculator::add((string) $transaction->getFee(), (string) $site_balance[$currency_code], 2);

    // Update site balance.
    $this->connection->merge('commerce_funds_user_funds')
      ->key(['uid' => 1])
      ->updateFields([
        'balance' => serialize($site_balance),
      ])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function loadAccountBalance(AccountInterface $account) {
    // Load site balance if user can administer transactions.
    if ($account->hasPermission('administer transactions')) {
      return $this->loadSiteBalance($account);
    }
    // Check if issuer balance exists.
    $balance_exist = $this->connection->query("SELECT * FROM {commerce_funds_user_funds} WHERE uid = :uid", [
      ':uid' => $account->id(),
    ])->fetchObject();

    // Unserialize balance.
    $balance = $balance_exist ? unserialize($balance_exist->balance) : [];

    return $balance;
  }

  /**
   * {@inheritdoc}
   */
  public function loadSiteBalance() {
    // Check if issuer balance exists.
    $balance_exist = $this->connection->query("SELECT * FROM {commerce_funds_user_funds} WHERE uid = :uid", [
      ':uid' => 1,
    ])->fetchObject();

    // Unserialize balance.
    $balance = $balance_exist ? unserialize($balance_exist->balance) : [];

    return $balance;
  }

  /**
   * {@inheritdoc}
   */
  public function loadTransactionByHash($hash) {
    $transactions = $this->entityTypeManager->getStorage('commerce_funds_transaction')->loadByProperties(['hash' => $hash]);
    // @todo to be removed if unique in db.
    if (count($transactions) > 1) {
      $duplicate_ids = '';
      foreach ($transactions as $transaction) {
        $duplicate_ids .= $transaction->id() . ', ';
      }
      throw new TransactionException(sprintf("Transaction duplicate error: %s", $duplicate_ids));
    }

    return reset($transactions);
  }

  /**
   * {@inheritdoc}
   */
  public function getTransactionCurrency($transaction_id) {

    $currency = Transaction::load($transaction_id)->getCurrency();

    return $currency;
  }

  /**
   * {@inheritdoc}
   */
  public function getConversionFromCurrency($transaction_id) {

    $currency = Transaction::load($transaction_id)->getFromCurrency();

    return $currency;
  }

  /**
   * {@inheritdoc}
   */
  public function sendTransactionMails(TransactionInterface $transaction) {
    // Prepares variables for later.
    $langcode = $this->config->get('system.site')->get('langcode');
    $config = $this->config->get('commerce_funds.settings');
    $issuer = $transaction->getIssuer();
    $recipient = $transaction->getRecipient();
    $transaction_type = $transaction->bundle();
    $transaction_status = $transaction->getStatus();
    // Mapping of configuration.
    // [transaction_type => [mail config name => mail type]].
    $config_mapping = [
      'transfer' => ['transfer' => ['_recipient', '_issuer']],
      'escrow' => [
        'escrow_created' => ['_recipient', '_issuer'],
        'escrow_canceled_by_issuer' => ['_recipient', '_issuer'],
        'escrow_canceled_by_recipient' => ['_recipient', '_issuer'],
        'escrow_released' => ['_recipient', '_issuer'],
      ],
      'withdrawal_request' => [
        'withdrawal_declined' => ['_issuer'],
        'withdrawal_approved' => ['_issuer'],
      ],
    ];
    $mail_configs = $config_mapping[$transaction_type];

    // Map to specifities for each transaction type.
    if ($transaction_type == 'transfer' && $transaction_status == $transaction::TRANSACTION_STATUS['completed']) {
      $config_name = 'transfer';
    }
    elseif ($transaction_type == 'escrow') {
      switch ($transaction_status) {
        default:
          $config_name = NULL;
          break;
        case $transaction::TRANSACTION_STATUS['pending']:
          $config_name = 'escrow_created';
          break;

        case $transaction::TRANSACTION_STATUS['canceled']:
          $config_name = $this->currentUser->id() == $issuer->id() ? 'escrow_canceled_by_issuer' : 'escrow_canceled_by_recipient';
          break;

        case $transaction::TRANSACTION_STATUS['completed']:
          $config_name = 'escrow_released';
      }
    }
    elseif ($transaction_type == 'withdrawal_request') {
      switch ($transaction_status) {
        default:
          $config_name = NULL;
          break;
        case $transaction::TRANSACTION_STATUS['canceled']:
          $config_name = 'withdrawal_declined';
          break;

        case $transaction::TRANSACTION_STATUS['completed']:
          $config_name = 'withdrawal_approved';
          break;
      }
    }
    else {
      $config_name = NULL;
    }

    // Stop here if no config.
    if (!$config_name) {
      return FALSE;
    }

    // Send mail for each mail type.
    foreach ($mail_configs[$config_name] as $type) {
      // Find recipient of email.
      $mail_recipient = strpos('issuer', $type) ? $issuer : $recipient;
      // Clean type for withdrawal requests.
      if ($transaction_type == 'withdrawal_request') {
        $type = '';
      }
      if ($config->get('mail_' . $config_name . $type)['activated']) {
        $balance = $this->loadAccountBalance($mail_recipient);
        $params = [
          'id' => $config_name . $type,
          'subject' => $this->token->replace($config->get('mail_' . $config_name . $type)['subject'], ['commerce_funds_transaction' => $transaction]),
          'body' => $this->token->replace($config->get('mail_' . $config_name . $type)['body']['value'], [
            'commerce_funds_transaction' => $transaction,
            'commerce_funds_balance' => $balance,
            'commerce_funds_balance_uid' => $mail_recipient->id(),
          ]),
        ];
        $email_sent[] = $this->mailManager->mail('commerce_funds', 'commerce_funds_transaction', $mail_recipient->getEmail(), $langcode, $params, NULL, TRUE);
      }
    }

    return !empty($email_sent) ? count($email_sent) : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function generateConfirmationMessage(TransactionInterface $transaction) {
    $fee = $transaction->getFee();
    $status = $transaction->getStatus();
    $currency = $transaction->getCurrency();

    switch ($transaction->bundle()) {
      case 'transfer':
        if ((float) $fee) {
          $confirmation_message = 'You have transfered @amount @currency to @recipient (fees: %commission @currency).';
        }
        else {
          $confirmation_message = 'You have transfered @amount @currency to @recipient.';
        }
        break;

      case 'escrow':
        if ($status == $transaction::TRANSACTION_STATUS['pending']) {
          if ((float) $fee) {
            $confirmation_message = 'Escrow payment of @amount @currency successfully created to @recipient (fees: %commission @currency).';
          }
          else {
            $confirmation_message = 'Escrow payment of @amount @currency successfully created to @recipient.';
          }
        }
        if ($status == $transaction::TRANSACTION_STATUS['completed']) {
          if ((float) $fee) {
            $confirmation_message = 'You have transfered @amount @currency to @recipient (fees: %commission @currency).';
          }
          else {
            $confirmation_message = 'You have transfered @amount @currency to @recipient.';
          }
        }
        if ($status == $transaction::TRANSACTION_STATUS['canceled']) {
          if ($this->currentUser->id() == $transaction->getIssuerId()) {
            $confirmation_message = 'Escrow payment of @amount to @recipient has been canceled.';
          }
          if ($this->currentUser->id() == $transaction->getRecipientId()) {
            $confirmation_message = 'Escrow payment of @amount from @issuer has been canceled.';
          }

        }
        break;

      case 'withdrawal_request':
        if ($status == $transaction::TRANSACTION_STATUS['pending']) {
          if ((float) $fee) {
            $confirmation_message = 'Withdrawal request sent. An extra commission of %commission @currency will be apllied to your withraw.';
          }
          else {
            $confirmation_message = 'Withdrawal request sent.';
          }
        }
        if ($status == $transaction::TRANSACTION_STATUS['declined']) {
          $confirmation_message = 'Request declined.';
        }
        if ($status == $transaction::TRANSACTION_STATUS['approved']) {
          $confirmation_message = 'Request approved.';
        }
        break;

      case 'conversion':
        if ($status == $transaction::TRANSACTION_STATUS['completed']) {
          $confirmation_message = '@amount_left @currency_left converted into @amount_right @currency_right.';
        }
    }

    if (!empty($confirmation_message)) {
      // phpcs:ignore.
      $this->messenger->addMessage($this->t($confirmation_message, [
        '@amount' => $currency->getSymbol() . $transaction->getBrutAmount(),
        '@amount_left' => $transaction->getFromCurrency() ? $transaction->getFromCurrency()->getSymbol() . $transaction->getBrutAmount() : '',
        '@amount_right' => $currency->getSymbol() . $transaction->getNetAmount(),
        '@currency_left' => $transaction->getFromCurrency() ? $transaction->getFromCurrency()->getCurrencyCode() : '',
        '@currency_right' => $currency->getCurrencyCode(),
        '@currency' => $currency->getCurrencyCode(),
        '@recipient' => $transaction->getRecipient()->getAccountName(),
        '@issuer' => $transaction->getIssuer()->getAccountName(),
        '%commission' => $currency->getSymbol() . number_format($fee, 2),
      ]), 'status');

      return TRUE;
    }

    return FALSE;
  }

}
