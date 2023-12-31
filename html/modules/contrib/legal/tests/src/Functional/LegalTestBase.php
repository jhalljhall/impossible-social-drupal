<?php

namespace Drupal\Tests\legal\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\legal\Entity\Conditions;
use Drupal\filter\Entity\FilterFormat;

/**
 * Provides setup and helper methods for Legal module tests.
 *
 * @group legal
 */
abstract class LegalTestBase extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['legal', 'filter'];

  /**
   * The user account.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * Login details.
   *
   * @var array
   */
  protected $loginDetails;

  /**
   * The user ID.
   *
   * @var int
   */
  protected $uid;

  /**
   * Conditions.
   *
   * @var string
   */
  protected $conditions;

  /**
   * Conditions plain text.
   *
   * @var string
   */
  protected $conditionsPlainText;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {

    parent::setUp();

    // Suppress Drush output errors.
    $this->setOutputCallback(function () {
    });

    // Create Full HTML text format.
    $full_html_format = FilterFormat::create([
      'format' => 'full_html',
      'name'   => 'Full HTML',
    ]);

    $full_html_format->save();

    // Create a user.
    $this->account = $this->drupalCreateUser([]);
    // Activate user by logging in.
    $this->drupalLogin($this->account);

    // Get login details of new user.
    $this->loginDetails['name'] = $this->account->getAccountName();
    $this->loginDetails['pass'] = $this->account->pass_raw;
    $this->uid                  = $this->account->id();

    $this->drupalLogout();

    // Legal settings.
    $language                  = 'en';
    $version                   = legal_version('version', $language);
    $this->conditions          = '<div class="legal-html-text">Lorem ipsum.</div>';
    $this->conditionsPlainText = 'Lorem ipsum.';
    $extras                    = 'a:10:{s:8:"extras-1";s:0:"";s:8:"extras-2";s:0:"";s:8:"extras-3";s:0:"";s:8:"extras-4";s:0:"";s:8:"extras-5";s:0:"";s:8:"extras-6";s:0:"";s:8:"extras-7";s:0:"";s:8:"extras-8";s:0:"";s:8:"extras-9";s:0:"";s:9:"extras-10";s:0:"";}';

    // Create T&C.
    Conditions::create([
      'version'    => $version['version'],
      'revision'   => $version['revision'],
      'language'   => $language,
      'conditions' => $this->conditions,
      'format'     => 'full_html',
      'date'       => time(),
      'extras'     => $extras,
      'changes'    => '',
    ])->save();

  }


}
