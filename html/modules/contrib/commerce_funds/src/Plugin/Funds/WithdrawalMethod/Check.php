<?php

namespace Drupal\commerce_funds\Plugin\Funds\WithdrawalMethod;

use Drupal\Core\Locale\CountryManager;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserDataInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides check withdrawal method.
 *
 * @WithdrawalMethod(
 *   id = "check",
 *   name = @Translation("Check"),
 * )
 */
class Check extends ConfigFormBase {

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
    return 'commerce_funds_withdrawal_check';
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
    $check_user_data = $this->userData->get('commerce_funds', $user->id(), 'check');
    // Decrypts data if encrypted.
    if ($this->moduleHandler->moduleExists('encrypt')) {
      $encryption_profile_id = $this->config('commerce_funds.settings')->get('encryption_profile');
      if ($encryption_profile_id && $check_user_data) {
        $encryption_profile = $this->entityTypeManager->getStorage('encryption_profile')->load($encryption_profile_id);
        foreach ($check_user_data as $key => $data) {
          $check_user_data[$key] = \Drupal::service('encryption')->decrypt($data, $encryption_profile);
        }
      }
    }

    $countries = CountryManager::getStandardList();

    $form['sub_title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Check informations'),
    ];

    $form['check_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Full Name'),
      '#description' => $this->t('Full Name to write the Check to'),
      '#default_value' => $check_user_data['check_name'] ?? '',
      '#size' => 40,
      '#maxlength' => 128,
      '#required' => TRUE,
    ];

    $form['check_country'] = [
      '#type' => 'select',
      '#title' => $this->t('Country'),
      '#options' => $countries,
      '#default_value' => $check_user_data['check_country'] ?? '',
      '#description' => '',
      '#required' => TRUE,
    ];

    $form['check_address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Address'),
      '#description' => $this->t('Detailed address to send the check to'),
      '#default_value' => $check_user_data['check_address'] ?? '',
      '#size' => 60,
      '#maxlength' => 128,
      '#required' => TRUE,
    ];

    $form['check_address2'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Address 2'),
      '#description' => $this->t('Detailed address to send the check to'),
      '#default_value' => $check_user_data['check_address2'] ?? '',
      '#size' => 60,
      '#maxlength' => 128,
    ];

    $form['check_city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#description' => '',
      '#default_value' => $check_user_data['check_city'] ?? '',
      '#size' => 20,
      '#maxlength' => 128,
      '#required' => TRUE,
    ];

    $form['check_province'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Province'),
      '#description' => '',
      '#default_value' => $check_user_data['check_province'] ?? '',
      '#size' => 20,
      '#maxlength' => 128,
    ];

    $form['check_postalcode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Postal Code'),
      '#description' => '',
      '#default_value' => $check_user_data['check_postalcode'] ?? '',
      '#size' => 20,
      '#maxlength' => 128,
      '#required' => TRUE,
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
  public function validateForm(array &$form, FormStateInterface $form_state) {}

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
    $this->userData->set('commerce_funds', $user->id(), 'check', $encrypted_values ?? $values);

    $this->messenger->addMessage(
      $this->t('Withdrawal method successfully updated.'),
      'status'
    );
  }

}
