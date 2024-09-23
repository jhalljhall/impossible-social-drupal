<?php

namespace Drupal\Tests\legal\Functional;

use Drupal\filter\Entity\FilterFormat;
use Drupal\legal\Entity\Accepted;
use Drupal\legal\Entity\Conditions;
use Drupal\Tests\BrowserTestBase;

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
   * A user, who has not accepted the legal terms yet.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $legalNotAcceptedUser;

  /**
   * A user, who has accepted the legal terms.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $legalAcceptedUser;

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

    // Create Full HTML text format.
    $full_html_format = FilterFormat::create([
      'format' => 'full_html',
      'name'   => 'Full HTML',
    ]);
    $full_html_format->save();

    // Create a user, who hasn't accepted the legal terms yet:
    $this->legalNotAcceptedUser = $this->drupalCreateUser([]);

    // Create a user, who accepted the legal terms:
    $this->legalAcceptedUser = $this->drupalCreateUser([]);

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

    // Let the legalAcceptedUser accept the legal terms:
    Accepted::create([
      'version'  => $version['version'],
      'revision' => $version['revision'],
      'language' => $language,
      'uid'      => $this->legalAcceptedUser->id(),
      'accepted' => time(),
    ])->save();
  }

}
