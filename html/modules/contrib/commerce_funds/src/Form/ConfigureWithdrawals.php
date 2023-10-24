<?php

namespace Drupal\commerce_funds\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\user\UserDataInterface;
use Drupal\user\Entity\User;
use Drupal\commerce_funds\WithdrawalMethodManagerInterface;
use Drupal\encrypt\Entity\EncryptionProfile;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to configure the withdrawals methods allowed.
 */
class ConfigureWithdrawals extends ConfigFormBase {

  /**
   * The withdrawal manager.
   *
   * @var \Drupal\commerce_funds\WithdrawalMethodManager
   */
  protected $withdrawalManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * Class constructor.
   */
  public function __construct(WithdrawalMethodManagerInterface $withdrawal_manager, ModuleHandlerInterface $module_handler, UserDataInterface $user_data) {
    $this->withdrawalManager = $withdrawal_manager;
    $this->moduleHandler = $module_handler;
    $this->userData = $user_data;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.withdrawal_method'),
      $container->get('module_handler'),
      $container->get('user.data')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_funds_configure_withdrawal_methods';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'commerce_funds.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('commerce_funds.settings');
    $methods = $this->withdrawalManager->getDefinitions();
    foreach ($methods as $key => $method) {
      $readable_methods[$key] = $method['name']->render();
    }

    $form['methods'] = [
      '#type' => 'checkboxes',
      '#options' => $readable_methods,
      '#default_value' => $config->get('withdrawal_methods'),
      '#title' => $this->t('Allowed methods'),
      '#description' => $this->t('Choose payment methods allowed for withdrawals.'),
    ];

    $form['encrypt'] = [
      '#type' => 'details',
      '#title' => $this->t('User data encryption'),
      '#open' => TRUE,
    ];

    if (!$this->moduleHandler->moduleExists('encrypt')) {
      $form['encrypt']['disclaimer'] = [
        '#markup' => $this->t('For security improvements, it\'s highly recommended using the <a href="@encrypt-url" target="_blank">Encrypt</a> module. This will ensure all user withdrawal methods information to be encrypted in the database.', [
          '@encrypt-url' => 'https://www.drupal.org/project/encrypt',
        ]),
      ];
    }
    else {
      // Build encryption profile select list.
      $options = [];
      $encryption_profiles = EncryptionProfile::loadMultiple();
      foreach ($encryption_profiles as $encryption_profile) {
        $options[$encryption_profile->id()] = $encryption_profile->label();
      }
      $form['encrypt']['encryption_profile'] = [
        '#type' => 'select',
        '#title' => $this->t('Encryption profile'),
        '#description' => $this->t('<strong>Warning!</strong> Changing the encryption profile or disabling it after data got encrypted will result in data loss!'),
        '#options' => $options,
        '#empty_value' => '',
        '#default_value' => $config->get('encryption_profile'),
      ];
      // Disable encryption profile if one exists.
      if ($config->get('encryption_profile')) {
        $form['encrypt']['encryption_profile'] += [
          '#attributes' => [
            'disabled' => 'disabled',
          ],
        ];
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->cleanValues()->getValues();
    // Set withdrawal methods config.
    $this->config('commerce_funds.settings')->set('withdrawal_methods', $values['methods']);

    if (!empty($values['encryption_profile']) && $values['encryption_profile'] != $form['encrypt']['encryption_profile']['#default_value']) {
      // Set encryption profile config.
      $this->config('commerce_funds.settings')->set('encryption_profile', $values['encryption_profile']);

      $users = User::loadMultiple();
      $user_chunks = array_chunk($users, 10);
      foreach ($user_chunks as $user_chunk) {
        $operations[] = [
          [get_class($this), 'encryptingUserDataBatch'],
          [$user_chunk],
        ];
      }
      $batch = [
        'title' => t('Encrypting previous data...'),
        'operations' => $operations,
        'init_message'     => t('Starting'),
        'progress_message' => t('Processed @current out of @total.'),
        'error_message'    => t('An error occurred during processing'),
      ];

      batch_set($batch);
    }

    $this->config('commerce_funds.settings')->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Encrypt previous user data.
   *
   * This function fetch all user withdrawal data,
   * encrypt them and resave them encrypted.
   */
  public static function encryptingUserDataBatch($user_chunk, &$context) {
    // We hard code withdrawal methods as we
    // don't know other plugins that might have been added.
    $data_types = ['bank_account', 'check', 'paypal', 'skrill'];
    $user_data_service = \Drupal::service('user.data');
    foreach ($user_chunk as $user) {
      foreach ($data_types as $data_type) {
        $user_data = $user_data_service->get('commerce_funds', $user->id(), $data_type);
        $encryption_profile_id = \Drupal::config('commerce_funds.settings')->get('encryption_profile');
        if ($user_data) {
          $encryption_profile = \Drupal::entityTypeManager()->getStorage('encryption_profile')->load($encryption_profile_id);
          foreach ($user_data as $key => $data) {
            $user_data[$key] = \Drupal::service('encryption')->encrypt($data, $encryption_profile);
          }
          $user_data = $user_data_service->set('commerce_funds', $user->id(), $data_type, $user_data);
        }
      }
    }
  }

}
