<?php

namespace Drupal\Tests\legal\Functional;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

/**
 * Tests password reset workflow when T&Cs need to be accepted.
 *
 * @group legal
 */
class PasswordResetTest extends LegalTestBase {

  use AssertMailTrait {
    getMails as drupalGetMails;
  }

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Set the last login time that is used to generate the one-time link so
    // that it is definitely over a second ago.
    $this->account->login = \Drupal::time()->getRequestTime() - mt_rand(10, 100000);
    \Drupal::database()->update('users_field_data')
      ->fields(['login' => $this->account->getLastLoginTime()])
      ->condition('uid', $this->account->id())
      ->execute();

  }

  /**
   * Test loging in with default Legal seetings.
   */
  public function testPasswordReset() {

    // Reset the password by username via the password reset page.
    $this->drupalGet('user/password');
    $edit['name'] = $this->loginDetails['name'];
    $this->submitForm($edit, 'Submit');

    // Get one time login URL from email (assume the most recent email).
    $_emails = $this->drupalGetMails();
    $email = end($_emails);
    $urls = [];
    preg_match('#.+user/reset/.+#', $email['body'], $urls);

    // Use one time login URL.
    $this->drupalGet($urls[0]);

    // Log in.
    $this->submitForm([], 'Log in', 'user-pass-reset');

    // Check user is redirected to T&C acceptance page.
    $expected_query = [
      'destination' => $this->account->toUrl('edit-form')->toString(),
      'token' => '',
    ];
    $expected_url = Url::fromRoute('legal.legal_login', [], ['query' => $expected_query])->setAbsolute()->toString();
    $this->assertStringStartsWith($expected_url, $this->getUrl());
    $this->assertSession()->statusCodeEquals(200);

    // Accept T&Cs and submit form.
    $edit = ['edit-legal-accept' => TRUE];
    $this->submitForm($edit, 'Confirm', 'legal-login');

    // Check user is logged in.
    $account = User::load($this->uid);
    $this->drupalUserIsLoggedIn($account);

    // Check user is redirected to their user page.
    $current_url = $this->getUrl();
    $expected_url = $this->baseUrl . '/user/' . $this->uid . '/edit?check_logged_in=1';
    $this->assertEquals($expected_url, $current_url);
  }

}
