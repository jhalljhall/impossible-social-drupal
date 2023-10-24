<?php

namespace Drupal\commerce_funds\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_funds\Entity\Transaction;
use Drupal\commerce_funds\TransactionManagerInterface;
use Drupal\commerce_funds\FeesManagerInterface;
use Drupal\commerce_funds\AvailableCurrenciesTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to transfer money to another user account.
 */
class FundsTransfer extends FormBase {

  use AvailableCurrenciesTrait;

  /**
   * The current account.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The fees manager.
   *
   * @var \Drupal\commerce_funds\FeesManagerInterface
   */
  protected $feesManager;

  /**
   * The transaction manager.
   *
   * @var \Drupal\commerce_funds\TransactionManagerInterface
   */
  protected $transactionManager;

  /**
   * Class constructor.
   */
  public function __construct(AccountProxy $current_user, EntityTypeManagerInterface $entity_type_manager, FeesManagerInterface $fees_manager, TransactionManagerInterface $transaction_manager) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->feesManager = $fees_manager;
    $this->transactionManager = $transaction_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('commerce_funds.fees_manager'),
      $container->get('commerce_funds.transaction_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_funds_transfer_funds';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'commerce_funds.transfer',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $fees_description = $this->feesManager->printTransactionFees('transfer');

    $form['amount'] = [
      '#type' => 'number',
      '#min' => 0.0,
      '#title' => $this->t('Transfer Amount'),
      '#description' => $fees_description,
      '#step' => 0.01,
      '#default_value' => 0.0,
      '#size' => 30,
      '#maxlength' => 128,
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['funds-amount'],
      ],
    ];

    if (isset($fees_description) && $this->config('commerce_funds.settings')->get('global')['add_rt_fee_calculation']) {
      $form['amount'] += [
        '#attached' => [
          'library' => ['commerce_funds/calculate_fees'],
          'drupalSettings' => [
            'funds' => ['fees' => $fees_description],
          ],
        ],
      ];
    }

    $form['currency'] = $this->currencySelectForm($form, $form_state);

    $form['username'] = [
      '#id' => 'commerce-funds-transfer-to',
      '#title' => $this->t('Transfer To'),
      '#description' => $this->t('Please enter a username.'),
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#required' => TRUE,
      '#size' => 30,
      '#maxlength' => 128,
      '#selection_settings' => [
        'include_anonymous' => FALSE,
      ],
    ];

    $form['notes'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Notes'),
      '#description' => $this->t('Eventually add a message to the recipient.'),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Transfer funds'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $amount = $form_state->getValue('amount');
    $currency = $form_state->getValue('currency');
    $fee_applied = $this->feesManager->calculateTransactionFee($amount, $currency, 'transfer');

    $issuer = $this->currentUser;
    $issuer_balance = $this->transactionManager->loadAccountBalance($issuer->getAccount(), $currency);
    $currency_balance = $issuer_balance[$currency] ?? 0;

    // Error if amount equals 0.
    if ($amount == 0) {
      $form_state->setErrorByName('amount', $this->t('Amount must be a positive number.'));
      return;
    }

    // Error if the user doesn't have enough money to cover the transfer + fee.
    if ($currency_balance < $fee_applied['net_amount']) {
      if (!$fee_applied['fee']) {
        $form_state->setErrorByName('amount', $this->t("Not enough funds to cover this transfer."));
      }
      if ($fee_applied['fee']) {
        $form_state->setErrorByName('amount', $this->t("Not enough funds to cover this transfer (Total: %total @currency).", [
          '%total' => $fee_applied['net_amount'],
          '@currency' => $currency,
        ]));
      }
    }

    // Error if user try to send money to itself.
    if ($recipient_id = $form_state->getValue('username')) {
      $recipient = $this->entityTypeManager->getStorage('user')->load($recipient_id);
      if ($issuer->id() == $recipient->id()) {
        $form_state->setErrorByName('username', $this->t("Operation impossible. You can't transfer money to yourself."));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $amount = $form_state->getValue('amount');
    $currency = $form_state->getValue('currency');
    $fee_applied = $this->feesManager->calculateTransactionFee($amount, $currency, 'transfer');

    $issuer = $this->currentUser;
    /** @var \Drupal\user\Entity\User $recipient */
    $recipient = $this->entityTypeManager->getStorage('user')->load($form_state->getValue('username'));

    $transaction = Transaction::create([
      'issuer' => $issuer->id(),
      'recipient' => $recipient->id(),
      'type' => 'transfer',
      'method' => 'internal',
      'brut_amount' => $amount,
      'net_amount' => $fee_applied['net_amount'],
      'fee' => $fee_applied['fee'],
      'currency' => $currency,
      'status' => Transaction::TRANSACTION_STATUS['canceled'],
      'notes' => [
        'value' => $form_state->getValue('notes')['value'],
        'format' => $form_state->getValue('notes')['format'],
      ],
    ]);
    $transaction->save();

    // Performs transaction.
    $this->transactionManager->performTransaction($transaction);
    // Send an emails.
    $this->transactionManager->sendTransactionMails($transaction);
    // Generate confirmation message.
    $this->transactionManager->generateConfirmationMessage($transaction);
  }

}
