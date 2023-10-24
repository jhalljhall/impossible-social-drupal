<?php

namespace Drupal\commerce_funds\Plugin\Funds\WithdrawalMethod;

use Drupal\Core\Locale\CountryManager;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserDataInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraints\Iban;
use Symfony\Component\Validator\Constraints\Bic;
use Symfony\Component\Validator\Validation;

/**
 * Provides bank account withdrawal method.
 *
 * @WithdrawalMethod(
 *   id = "bank-account",
 *   name = @Translation("Bank account"),
 * )
 */
class BankAccount extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The route.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The current account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * The messenger service.
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
   * Class constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RouteMatchInterface $route_match, AccountProxyInterface $current_user, UserDataInterface $user_data, MessengerInterface $messenger, ModuleHandlerInterface $module_handler) {
    $this->entityTypeManager = $entity_type_manager;
    $this->routeMatch = $route_match;
    $this->currentUser = $current_user;
    $this->userData = $user_data;
    $this->messenger = $messenger;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
      $container->get('current_user'),
      $container->get('user.data'),
      $container->get('messenger'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_funds_withdrawal_bank_account';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'commerce_funds.withdrawal_methods',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Allow the plugin to be used outside
    // of route with user as parameter.
    $user = $this->routeMatch->getParameter('user') ?? User::load($this->currentUser->id());
    // Anonymous shouldn't see this form.
    if (!$user) {
      return [];
    }
    $bank_user_data = $this->userData->get('commerce_funds', $user->id(), 'bank_account');
    // Decrypts data if encrypted.
    if ($this->moduleHandler->moduleExists('encrypt')) {
      $encryption_profile_id = $this->config('commerce_funds.settings')->get('encryption_profile');
      if ($encryption_profile_id && $bank_user_data) {
        $encryption_profile = $this->entityTypeManager->getStorage('encryption_profile')->load($encryption_profile_id);
        foreach ($bank_user_data as $key => $data) {
          $bank_user_data[$key] = \Drupal::service('encryption')->decrypt($data, $encryption_profile);
        }
      }
    }

    $countries = CountryManager::getStandardList();

    $form['sub_title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Bank account informations'),
    ];

    $form['account_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of Account Holder'),
      '#default_value' => $bank_user_data['account_name'] ?? '',
      '#size' => 40,
      '#maxlength' => 128,
      '#required' => TRUE,
    ];

    $form['account_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account Number / IBAN'),
      '#default_value' => $bank_user_data['account_number'] ?? '',
      '#size' => 40,
      '#maxlength' => 34,
      '#required' => TRUE,
    ];

    $form['bank_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bank Name'),
      '#default_value' => $bank_user_data['bank_name'] ?? '',
      '#size' => 40,
      '#maxlength' => 128,
      '#required' => TRUE,
    ];

    $form['bank_country'] = [
      '#type' => 'select',
      '#title' => $this->t('Bank Country'),
      '#options' => $countries,
      '#default_value' => $bank_user_data['bank_country'] ?? '',
      '#required' => TRUE,
    ];

    $form['swift_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Swift Code'),
      '#default_value' => $bank_user_data['swift_code'] ?? '',
      '#size' => 40,
      '#maxlength' => 128,
      '#required' => TRUE,
    ];

    $form['bank_address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bank Address'),
      '#default_value' => $bank_user_data['bank_address'] ?? '',
      '#size' => 40,
      '#maxlength' => 128,
    ];

    $form['bank_address2'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bank Address 2'),
      '#default_value' => $bank_user_data['bank_address2'] ?? '',
      '#size' => 40,
      '#maxlength' => 128,
    ];

    $form['bank_city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bank City'),
      '#default_value' => $bank_user_data['bank_city'] ?? '',
      '#size' => 20,
      '#maxlength' => 128,
    ];

    $form['bank_province'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bank Province'),
      '#default_value' => $bank_user_data['bank_province'] ?? '',
      '#size' => 20,
      '#maxlength' => 128,
    ];

    $form['bank_postalcode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bank Postal Code'),
      '#default_value' => $bank_user_data['bank_postalcode'] ?? '',
      '#size' => 20,
      '#maxlength' => 128,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save informations'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->cleanValues()->getValues();
    // Validates IBAN.
    if (strpos($values['account_number'], $values['bank_country']) !== 0) {
      $form_state->setErrorByName('account_number', $this->t('The IBAN does not match the bank country.'));
    }
    $validator = Validation::createValidator();
    $violations = $validator->validate($values['account_number'], [new Iban()]);
    foreach ($violations as $violation) {
      // phpcs:ignore
      $form_state->setErrorByName('account_number', $this->t($violation->getMessage()));
    }

    // Validates BIC/SWIFT.
    if (strpos($values['swift_code'], $values['bank_country']) !== 4) {
      $form_state->setErrorByName('swift_code', $this->t('The SWIFT code does not match the bank country.'));
    }
    $violations = $validator->validate($values['swift_code'], [new Bic()]);
    foreach ($violations as $violation) {
      // phpcs:ignore
      $form_state->setErrorByName('swift_code', $this->t($violation->getMessage()));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user = $this->routeMatch->getParameter('user') ?? User::load($this->currentUser->id());
    $values = $form_state->cleanValues()->getValues();
    // Encrypts data if enabled.
    if ($this->moduleHandler->moduleExists('encrypt')) {
      $encryption_profile_id = $this->config('commerce_funds.settings')->get('encryption_profile');
      if ($encryption_profile_id) {
        $encryption_profile = $this->entityTypeManager->getStorage('encryption_profile')->load($encryption_profile_id);
        foreach ($values as $key => $value) {
          $encrypted_values[$key] = \Drupal::service('encryption')->encrypt($value, $encryption_profile);
        }
      }
    }
    $this->userData->set('commerce_funds', $user->id(), 'bank_account', $encrypted_values ?? $values);

    $this->messenger->addMessage(
      $this->t('Withdrawal method successfully updated.'),
      'status'
    );
  }

}
