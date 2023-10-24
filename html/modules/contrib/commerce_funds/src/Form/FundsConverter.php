<?php

namespace Drupal\commerce_funds\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\commerce_funds\Entity\Transaction;
use Drupal\commerce_funds\FeesManagerInterface;
use Drupal\commerce_funds\TransactionManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to convert currencies.
 */
class FundsConverter extends FormBase {

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
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

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
  public function __construct(AccountProxy $current_user, EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger, ModuleHandlerInterface $module_handler, FeesManagerInterface $fees_manager, TransactionManagerInterface $transaction_manager) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->moduleHandler = $module_handler;
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
    $container->get('messenger'),
    $container->get('module_handler'),
    $container->get('commerce_funds.fees_manager'),
    $container->get('commerce_funds.transaction_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_funds_converter';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'commerce_funds.converter',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form_state->setCached(FALSE);
    if ($this->moduleHandler->moduleExists('commerce_exchanger') && ($exchange_rates = $this->feesManager->getExchangeRates())) {
      $currencies = $this->entityTypeManager->getStorage('commerce_currency')->loadMultiple();
      /** @var \Drupal\commerce_price\Entity\Currency $currency */
      foreach ($currencies as $currency) {
        $currency_codes[$currency->getCurrencyCode()] = $currency->getCurrencyCode();
      }
      // Make sure currencies are sorted.
      ksort($currencies);

      $form['currency_left'] = [
        '#type' => 'select',
        '#title' => $this->t('From'),
        '#description' => $this->t('The currency to convert.'),
        '#options' => $currency_codes,
        '#ajax' => [
          'callback' => [get_class($this), 'printRate'],
          'event' => 'click',
          'wrapper' => 'exchange-rate',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Calculating rate...'),
          ],
        ],
      ];

      $form['amount'] = [
        '#type' => 'number',
        '#title' => $this->t('Amount'),
        '#description' => $this->t('Amount to convert.'),
        '#min' => 0.0,
        '#step' => 0.01,
        '#default_value' => 0,
        '#size' => 30,
        '#maxlength' => 128,
        '#required' => TRUE,
        '#ajax' => [
          'callback' => [get_class($this), 'printRate'],
          'event' => 'end_typing',
          'wrapper' => 'exchange-rate',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Calculating rate...'),
          ],
        ],
        '#attributes' => [
          'class'      => [
            'delayed-input-submit',
          ],
          'data-delay' => '400',
        ],
        '#attached' => [
          'library' => ['commerce_funds/delayed_submit'],
        ],
      ];

      $form['currency_right'] = [
        '#type' => 'select',
        '#title' => $this->t('To'),
        '#description' => $this->t('The to currency to convert into.'),
        '#options' => $currency_codes,
        '#ajax' => [
          'callback' => [get_class($this), 'printRate'],
          'event' => 'click',
          'wrapper' => 'exchange-rate',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Calculating rate...'),
          ],
        ],
      ];

      $form['ajax_container'] = [
        '#type'       => 'container',
        '#attributes' => ['id' => 'exchange-rate'],
      ];

      $rate_description = '';

      if (!empty($form_state->getUserInput())) {
        if ($form_state->getUserInput()['currency_left'] != $form_state->getUserInput()['currency_right']) {
          $new_amount = $this->feesManager->printConvertedAmount($form_state->getUserInput()['amount'], $form_state->getUserInput()['currency_left'], $form_state->getUserInput()['currency_right']);
          $rate_description = $this->t('Conversion rate applied: @exchange-rate% <br> Amount after conversion: @new_amount', [
            '@exchange-rate' => $exchange_rates[$form_state->getUserInput()['currency_left']][$form_state->getUserInput()['currency_right']]['value'] ?? '0',
            '@new_amount' => $new_amount,
          ]);
        }
      }

      $form['ajax_container']['markup'] = [
        '#markup' => $rate_description ?: $this->t('Conversion rate applied: 1%'),
        '#attributes' => [
          'id' => ['rate-output'],
        ],
      ];

      $form['actions'] = ['#type' => 'actions'];
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Convert funds'),
      ];
    }
    else {
      $this->messenger->addError($this->t('No exchange rates found.'));
      // Set a message for users.
      $form['no_exchanges_rates'] = [
        '#markup' => $this->t('Sorry, no exchange rates are available at the moment.'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->isSubmitted()) {
      $amount = $form_state->getValue('amount');
      $currency = $form_state->getValue('currency_left');

      $issuer = $this->currentUser;
      $issuer_balance = $this->transactionManager->loadAccountBalance($issuer->getAccount(), $currency);
      $currency_balance = $issuer_balance[$currency] ?? 0;

      // Error if amount equals 0.
      if ($amount == 0) {
        $form_state->setErrorByName('amount', $this->t('Amount must be a positive number.'));
        return;
      }

      // Error if the user doesn't have enought money to cover conversion.
      if ($currency_balance < $amount) {
        $form_state->setErrorByName('amount', $this->t('Not enough funds to cover this conversion.'));
      }

      // You can't convert a currency into intself.
      if ($currency === $form_state->getValue('currency_right')) {
        $form_state->setErrorByName('currency_right', $this->t('Operation impossible. Please chose another currency.'));
      }
      else {
        // No exchange rates.
        if (!$this->feesManager->getExchangeRates()) {
          $form_state->setErrorByName('currency_right', $this->t('Operation impossible. No exchange rates found.'));
        }
        else {
          // If amount after conversion equals 0.
          $conversion = $this->feesManager->convertCurrencyAmount($amount, $currency, $form_state->getValue('currency_right'));
          if (!(float) $conversion['new_amount']) {
            $form_state->setErrorByName('currency_right', $this->t('Operation impossible. No exchange rates found.'));
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $amount = $form_state->getValue('amount');
    $conversion = $this->feesManager->convertCurrencyAmount($amount, $form_state->getValue('currency_left'), $form_state->getValue('currency_right'));

    $transaction = Transaction::create([
      'issuer' => $this->currentUser->id(),
      'recipient' => $this->currentUser->id(),
      'type' => 'conversion',
      'method' => 'internal',
      'brut_amount' => $amount,
      'net_amount' => $conversion['new_amount'],
      'fee' => $conversion['rate'],
      'from_currency' => $form_state->getValue('currency_left'),
      'currency' => $form_state->getValue('currency_right'),
      'status' => Transaction::TRANSACTION_STATUS['canceled'],
      'notes' => [
        'value' => $this->t('@amount @currency_left converted into @new_amount @currency_right.', [
          '@amount' => $amount,
          '@currency_left' => $form_state->getValue('currency_left'),
          '@new_amount' => $conversion['new_amount'],
          '@currency_right' => $form_state->getValue('currency_right'),
        ]),
        'format' => 'basic_html',
      ],
    ]);
    $transaction->save();

    $this->transactionManager->performTransaction($transaction);
    // Generate confirmation message.
    $this->transactionManager->generateConfirmationMessage($transaction);
  }

  /**
   * Ajax callback.
   */
  public static function printRate($form, FormStateInterface $form_state) {
    return $form['ajax_container'];
  }

}
