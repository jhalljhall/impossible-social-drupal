<?php

namespace Drupal\legal\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\legal\Entity\Accepted;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * After login display new T&Cs to user and require that they are agreed to.
 *
 * User has been logged out before arriving at this page,
 * and is logged back in if they accept T&Cs.
 */
class LegalLogin extends FormBase {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Language handling.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The account the shortcut set is for.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;
  protected $requestStack;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'legal_login';
  }

  /**
   * Constructs a new LegalLogin object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   */
  public function __construct(Connection $database, LanguageManagerInterface $language_manager, ModuleHandlerInterface $module_handler, RequestStack $request_stack, TimeInterface $time = NULL, CacheBackendInterface $cache) {
    $this->database = $database;
    $this->languageManager = $language_manager;
    $this->moduleHandler = $module_handler;
    $this->requestStack = $request_stack;
    $this->time = $time;
    $this->cache = $cache;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('language_manager'),
      $container->get('module_handler'),
      $container->get('request_stack'),
      $container->get('datetime.time'),
      $container->get('cache.menu'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config   = $this->config('legal.settings');
    $language = $this->languageManager->getCurrentLanguage();

    $id_hash = $_COOKIE['Drupal_visitor_legal_hash'];
    $uid     = $_COOKIE['Drupal_visitor_legal_id'];
    $token   = $_GET['token'];

    if (!is_numeric($uid)) {
      $this->setValidationError($form_state);
    }

    // Assert that the user ID is valid.
    $user = User::load($uid);

    if (!$user instanceof UserInterface) {
      return;
    }

    // Get last accepted version for this account.
    $legal_account = legal_get_accept($uid);

    // If no version accepted, get version with current language revision.
    if (empty($legal_account['version'])) {
      $conditions = legal_get_conditions($language->getId());
      // No conditions set yet.
      if (empty($conditions['conditions'])) {
        return;
      }
    }
    else {
      // Get version / revision of last accepted language.
      $conditions = legal_get_conditions($legal_account['language']);
      // No conditions set yet.
      if (empty($conditions['conditions'])) {
        return;
      }
      // Check latest version of T&C has been accepted.
      $accepted = legal_version_check($uid, $conditions['version'], $conditions['revision'], $legal_account);

      if ($accepted) {

        if ($config->get('accept_every_login') == 0) {
          return;
        }
        else {
          $request        = $this->requestStack->getCurrentRequest();
          $session        = $request->getSession();
          $newly_accepted = $session->get('legal_login', FALSE);

          if ($newly_accepted) {
            return;
          }
        }

      }
    }

    legal_display_fields($form, $conditions, 'login');

    $form['uid'] = [
      '#type'  => 'value',
      '#value' => $uid,
    ];

    $form['token'] = [
      '#type'  => 'value',
      '#value' => $token,
    ];

    $form['hash'] = [
      '#type'  => 'value',
      '#value' => $id_hash,
    ];

    $form['tc_id'] = [
      '#type'  => 'value',
      '#value' => $conditions['tc_id'],
    ];

    $form['version'] = [
      '#type'  => 'value',
      '#value' => $conditions['version'],
    ];

    $form['revision'] = [
      '#type'  => 'value',
      '#value' => $conditions['revision'],
    ];

    $form['language'] = [
      '#type'  => 'value',
      '#value' => $conditions['language'],
    ];

    $form = legal_display_changes($form, $uid);

    $form['save'] = [
      '#type'   => 'submit',
      '#value'  => t('Confirm'),
      '#weight' => 100,
    ];

    // Prevent this page from being cached.
    \Drupal::service('page_cache_kill_switch')->trigger();

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    $token = $form_state->getValue('token');

    $uid = $form_state->getValue('uid');

    if (!is_numeric($uid)) {
      $this->setValidationError($form_state);
    }

    $account = User::load($uid);

    if (!$account instanceof UserInterface) {
      $this->setValidationError($form_state);
    }

    $this->user = $account;

    $last_login = $account->get('login')->value;
    $password   = $account->get('pass')->value;
    $data       = $last_login . $uid . $password;

    $hash = Crypt::hmacBase64($data, $token);

    if ($hash != $form_state->getValue('hash')) {
      $this->setValidationError($form_state);
    }
  }

  /**
   * Triggers a validation error and calls exit().
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function setValidationError(FormStateInterface $form_state): void {
    $form_state->setErrorByName('legal_accept', $this->t('User ID cannot be identified.'));
    legal_deny_with_redirect();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    user_cookie_delete('legal_hash');
    user_cookie_delete('legal_id');

    $values   = $form_state->getValues();
    $user     = $this->user;
    $config   = $this->config('legal.settings');

    // Set redirect action for form submission.
    if (!empty($_GET['destination'])) {

      $destination_path = $_GET['destination'];

      // Make sure redirect destination starts with a slash.
      if (strpos($destination_path, '/') !== 0) {
        $destination_path = '/' . $destination_path;
      }

      // Use an existing destination if set.
      $redirect = Url::fromUserInput($destination_path);

      // Password reset actions.
      if (!empty($_GET['pass-reset-token'])) {
        // Store password reset token in session for \Drupal\user\AccountForm::form.
        $name = 'pass_reset_' . $user->id();
        $value = $_GET['pass-reset-token'];
        \Drupal::request()->getSession()->set($name, $value);

        // Clear any flood events for this user.
        \Drupal::service('flood')->clear('user.password_request_user', $user->id());

        // Set message reminding user to reset password.
        $message = t('You have just used your one-time login link. It is no
        longer necessary to use this link to log in. It is recommended that you
        set your password.');
        \Drupal::messenger()->addMessage($message);
      }
    }
    elseif ($config->get('login_redirect_url')) {
      // Redirect set in Legal configuration.
      $redirect = Url::fromUserInput($config->get('login_redirect_url'));
    }
    else {
      // Default redirect to the user's account page.
      $params   = ['user' => $values['uid']];
      $redirect = Url::fromRoute('entity.user.canonical', $params);
    }

    $form_state->setRedirectUrl($redirect);

    // Option to require user to accept T&Cs on every login.
    if ($config->get('accept_every_login') == '1') {

      // Set flag that user has accepted T&Cs again.
      $request = $this->requestStack->getCurrentRequest();
      $session = $request->getSession();
      $session->set('legal_login', TRUE);

      // Get last accepted version for this account.
      $legal_account    = legal_get_accept($values['uid']);
      $already_accepted = legal_version_check($values['uid'], $values['version'], $values['revision'], $legal_account);

      // If already accepted just update the time.
      if ($already_accepted) {
        $accepted = Accepted::load($legal_account['legal_id']);
        $accepted->set("accepted", time());
        $accepted->save();
      }
      else {
        legal_save_accept($values['version'], $values['revision'], $values['language'], $values['uid']);
      }
    }
    else {
      legal_save_accept($values['version'], $values['revision'], $values['language'], $values['uid']);
    }

    $this->logger('legal')
      ->notice('%name accepted T&C version %tc_id.', [
        '%name'  => $user->get('name')->getString(),
        '%tc_id' => $values['tc_id'],
      ]);

    // User has new permissions, so we clear their menu cache.
    $this->cache->delete($values['uid']);

    // Log user in.
    user_login_finalize($user);
  }

  /**
   * Access control callback.
   *
   * Check that access cookie and hash have been set.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   */
  public function access(AccountInterface $account) {

    // Check we have all the data and there are no shenanigans.
    if (!isset($_GET['token']) || !isset($_COOKIE['Drupal_visitor_legal_id']) || !is_numeric($_COOKIE['Drupal_visitor_legal_id']) || !isset($_COOKIE['Drupal_visitor_legal_hash'])) {
      return AccessResult::forbidden();
    }

    $uid     = $_COOKIE['Drupal_visitor_legal_id'];
    $visitor = User::load($uid);

    if (!$visitor instanceof UserInterface) {
      return AccessResult::forbidden();
    }

    $last_login = $visitor->get('login')->value;

    if (empty($last_login)) {
      return AccessResult::forbidden();
    }

    // Limit how long $id_hash can be used to 1 hour.
    // Timestamp and $id_hash are used to generate the authentication token.
    if (($this->time->getRequestTime() - $last_login) > 3600) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

}
