<?php

namespace Drupal\commerce_funds\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserDataInterface;
use Drupal\commerce_funds\Entity\Transaction;
use Drupal\commerce_funds\FeesManagerInterface;
use Drupal\commerce_funds\TransactionManagerInterface;
use Drupal\commerce_funds\AvailableCurrenciesTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Form to withdraw mmoney on user account.
 */
class FundsWithdraw extends FormBase {

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
   * The user data interface.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

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
  public function __construct(AccountProxy $current_user, EntityTypeManagerInterface $entity_type_manager, UserDataInterface $user_data, FeesManagerInterface $fees_manager, TransactionManagerInterface $transaction_manager) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->userData = $user_data;
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
      $container->get('user.data'),
      $container->get('commerce_funds.fees_manager'),
      $container->get('commerce_funds.transaction_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_funds_withdraw';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'commerce_funds.withdraw',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $methods = array_filter($this->config('commerce_funds.settings')->get('withdrawal_methods'));
    if (!$methods) {
      throw new NotFoundHttpException();
    }
    foreach ($methods as $key => $method) {
      $fee = $this->feesManager->printPaymentGatewayFees($key, $this->t('unit(s)'), 'withdraw') ?: '';
      $enabled_method['methods'][$key] = ucfirst($method) . ' ' . $fee;
    }

    $form['amount'] = [
      '#type' => 'number',
      '#min' => 0.0,
      '#title' => $this->t('Amount to withdraw'),
      '#description' => $this->t('Enter the amount you want to withdraw.'),
      '#default_value' => 0,
      '#step' => 0.01,
      '#size' => 30,
      '#maxlength' => 128,
      '#required' => TRUE,
    ];

    $form['currency'] = $this->currencySelectForm($form, $form_state);

    $form['methods'] = [
      '#type' => 'radios',
      '#options' => str_replace('-', ' ', $enabled_method['methods']),
      '#title' => $this->t('Select your preferred withdrawal method.'),
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit request'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $method = str_replace('-', '_', $form_state->getValue('methods'));
    $amount = $form_state->getValue('amount');
    $currency = $form_state->getValue('currency');
    $fee_applied = $this->feesManager->calculateTransactionFee($amount, $currency, 'withdraw');

    $issuer = $this->currentUser;
    $issuer_balance = $this->transactionManager->loadAccountBalance($issuer->getAccount(), $currency);
    $currency_balance = $issuer_balance[$currency] ?? 0;
    $issuer_data = $this->userData->get('commerce_funds', $issuer->id(), $method);

    // Error if amount equals 0.
    if ($amount == 0) {
      $form_state->setErrorByName('amount', $this->t('Amount must be a positive number.'));
      return;
    }

    // Error if the user doesn't have enough money.
    if ($amount > $currency_balance) {
      $form_state->setErrorByName('amount', $this->t("Your available balance is @balance @currency.", [
        '@balance' => $currency_balance,
        '@currency' => $currency,
      ]));
    }

    // Error if the user doesn't have enough money
    // to cover the withdrawal + fee.
    if ($fee_applied['net_amount'] > $currency_balance) {
      $form_state->setErrorByName('amount', $this->t('You cannot withdraw @amount @currency using this payment method. Commission is @fee @currency.', [
        '@amount' => $amount,
        '@currency' => $currency,
        '@fee' => $fee_applied['fee'] / 100,
      ]));
    }

    // No details available for this withdrawal method.
    if (!$issuer_data && $method) {
      $form_state->setErrorByName('methods', $this->t('Please <a href="@enter_details_link">enter your details</a> for this withdrawal method first.', [
        '@enter_details_link' => Url::fromRoute('commerce_funds.withdrawal_methods.edit', [
          'user' => $this->currentUser->id(),
          'method' => str_replace('_', '-', $method),
        ], [
          'query' => [
            'destination' => $this->getRequest()->getRequestUri(),
          ],
        ])->toString(),
      ]));
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $issuer = $this->currentUser;
    $method = $form_state->getValue('methods');
    $amount = $form_state->getValue('amount');
    $currency = $form_state->getValue('currency');
    $fee_applied = $this->feesManager->calculateTransactionFee($amount, $currency, 'withdraw_' . $method);

    $transaction = Transaction::create([
      'issuer' => $issuer->id(),
      'recipient' => $issuer->id(),
      'type' => 'withdrawal_request',
      'method' => $method,
      'brut_amount' => $amount,
      'net_amount' => $fee_applied['net_amount'],
      'fee' => $fee_applied['fee'],
      'currency' => $currency,
      'status' => Transaction::TRANSACTION_STATUS['pending'],
    ]);
    $transaction->save();

    // Generate confirmation message.
    $this->transactionManager->generateConfirmationMessage($transaction);
  }

}
