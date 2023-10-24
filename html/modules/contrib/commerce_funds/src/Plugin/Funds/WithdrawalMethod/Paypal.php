<?php

namespace Drupal\commerce_funds\Plugin\Funds\WithdrawalMethod;

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
 * Provides Paypal withdrawal method.
 *
 * @WithdrawalMethod(
 *   id = "paypal",
 *   name = @Translation("Paypal"),
 * )
 */
class Paypal extends ConfigFormBase {

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
    return 'commerce_funds_withdrawal_paypal';
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
    $paypal_user_data = $this->userData->get('commerce_funds', $user->id(), 'paypal');
    // Decrypts data if encrypted.
    if ($this->moduleHandler->moduleExists('encrypt')) {
      $encryption_profile_id = $this->config('commerce_funds.settings')->get('encryption_profile');
      if ($encryption_profile_id && $paypal_user_data) {
        $encryption_profile = $this->entityTypeManager->getStorage('encryption_profile')->load($encryption_profile_id);
        foreach ($paypal_user_data as $key => $data) {
          $paypal_user_data[$key] = \Drupal::service('encryption')->decrypt($data, $encryption_profile);
        }
      }
    }

    $form['sub_title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Paypal'),
    ];

    $form['paypal_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Paypal Email'),
      '#description' => $this->t('Withdrawals using Paypal will be sent to this email'),
      '#default_value' => $paypal_user_data['paypal_email'] ?? '',
      '#size' => 40,
      '#maxlength' => 64,
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
    $this->userData->set('commerce_funds', $user->id(), 'paypal', $encrypted_values ?? $values);

    $this->messenger->addMessage(
      $this->t('Withdrawal method successfully updated.'),
      'status'
    );
  }

}
