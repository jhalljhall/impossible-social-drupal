<?php

namespace Drupal\commerce_funds\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\user\UserDataInterface;
use Drupal\commerce_funds\TransactionManagerInterface;
use Drupal\commerce_price\Calculator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines a confirmation form to approve a withdrawal request.
 */
class ConfirmWithdrawalApproval extends ConfirmFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The transaction.
   *
   * @var \Drupal\commerce_funds\Entity\Transaction
   */
  protected $transaction;

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Class constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountProxy $current_user, TransactionManagerInterface $transaction_manager, RequestStack $request_stack, UserDataInterface $user_data, ModuleHandlerInterface $module_handler) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->transactionManager = $transaction_manager;
    $this->requestStack = $request_stack;
    $this->userData = $user_data;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('commerce_funds.transaction_manager'),
      $container->get('request_stack'),
      $container->get('user.data'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() : string {
    return "confirm_withdrawal_approval";
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
    return $this->t('Are you sure you want to approve request: %id?', ['%id' => $this->transaction->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $request_hash = NULL) {
    // Load the transaction.
    /** @var \Drupal\commerce_funds\Entity\Transaction $transaction */
    $this->transaction = $this->transactionManager->loadTransactionByHash($request_hash);
    // Add transaction details to form.
    $currency_code = $this->transaction->getCurrencyCode();
    $currency_symbol = $this->transaction->getCurrency()->getSymbol();
    $amount = $this->transaction->getNetAmount();
    $user_balance = $this->transactionManager->loadAccountBalance($this->transaction->getIssuer())[$currency_code] ?? 0;
    $transaction_solvable = Calculator::compare($user_balance, $amount) != -1 ? 'Yes' : 'No';

    $form['transaction'] = ['#markup' => $this->t('<h2>Transaction details</h2>')];
    $form += [
      'issuer' => [
        '#markup' => $this->t('Issuer: <a href="@issuer-url">@issuer</a> <br>', [
          '@issuer-url' => Url::fromRoute('entity.user.canonical', ['user' => $this->transaction->getIssuerId()])->toString(),
          '@issuer' => $this->transaction->getIssuer()->getAccountName(),
        ]),
      ],
      'amount' => [
        '#markup' => $this->t('Amount: @currency_symbol@amount @currency_code <br>', [
          '@amount' => $amount,
          "@currency_code" => $currency_code,
          "@currency_symbol" => $currency_symbol,
        ]),
      ],
      'user_balance' => [
        '#markup' => $this->t('Current balance: @currency_symbol@balance @currency_code <br>', [
          '@balance' => $user_balance,
          "@currency_code" => $currency_code,
          "@currency_symbol" => $currency_symbol,
        ]),
      ],
      'solvable' => [
        '#markup' => $this->t('Solvable: @solvable <br>', [
          '@solvable' => $transaction_solvable,
        ]),
      ],
    ];

    // Add method user data to form.
    $method = str_replace('-', '_', $this->transaction->getMethod());
    $method_user_data = $this->userData->get('commerce_funds', $this->transaction->getIssuerId(), $method);
    // Decrypts data if encrypted.
    if ($this->moduleHandler->moduleExists('encrypt')) {
      $encryption_profile_id = $this->config('commerce_funds.settings')->get('encryption_profile');
      if ($encryption_profile_id && $method_user_data) {
        $encryption_profile = $this->entityTypeManager->getStorage('encryption_profile')->load($encryption_profile_id);
      }
    }
    $form['methods'] = ['#markup' => $this->t('<h2>Method requested</h2>')];
    $form['method'] = [
      '#markup' => $this->t('Method: @method <br>', [
        '@method' => ucfirst(str_replace('-', ' ', $this->transaction->getMethod())),
      ]),
    ];
    foreach ($method_user_data as $key => $data) {
      $method_user_data[$key] = [
        '#markup' => $this->t('@key: @value <br>', [
          '@key' => ucfirst(str_replace('_', ' ', $key)),
          '@value' => isset($encryption_profile) ? \Drupal::service('encryption')->decrypt($data, $encryption_profile) : $data,
        ]),
      ];
    }

    $form = array_merge($form, $method_user_data ?? []);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Check if user have enough funds.
    $currency_code = $this->transaction->getCurrencyCode();
    $user_balance = $this->transactionManager->loadAccountBalance($this->transaction->getIssuer())[$currency_code] ?? 0;
    if (Calculator::compare($user_balance, $this->transaction->getNetAmount()) === -1) {
      $form_state->setError($form, $this->t('Not enough found.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $transaction = $this->transaction;

    // Perform transaction.
    $this->transactionManager->performTransaction($transaction);
    // Send an email to the requester.
    $this->transactionManager->sendTransactionMails($transaction);
    // Generate confirmation message.
    $this->transactionManager->generateConfirmationMessage($transaction);
    // Update transaction.
    $transaction->setStatus($transaction::TRANSACTION_STATUS['approved']);
    $transaction->save();

    // Set redirection.
    $form_state->setRedirect('view.commerce_funds_transactions.withdrawal_requests');
  }

}
