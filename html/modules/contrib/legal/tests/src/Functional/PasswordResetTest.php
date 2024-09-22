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
    $this->legalNotAcceptedUser->login = \Drupal::time()->getRequestTime() - mt_rand(10, 100000);
    \Drupal::database()->update('users_field_data')
      ->fields(['login' => $this->legalNotAcceptedUser->getLastLoginTime()])
      ->condition('uid', $this->legalNotAcceptedUser->id())
      ->execute();

  }

  /**
   * Test logging in with a user having already accepted the legal terms.
   */
  public function testPasswordResetWithLegalAccepted() {
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $message = 'You have just used your one-time login link. It is no longer necessary to use this link to log in. It is recommended that you set your password.';

    // Reset the password by username via the password reset page.
    $this->drupalGet('user/password');
    $edit['name'] = $this->legalAcceptedUser->getAccountName();
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

    // Check that the notice about the one-time login link is displayed:
    $this->assertSession()->pageTextContainsOnce($message);

    // Check user is logged in.
    $account = User::load($this->legalAcceptedUser->id());
    $this->drupalUserIsLoggedIn($account);

    // Check user is redirected to their user page.
    $current_url = $this->getUrl();
    $this->assertStringStartsWith($this->baseUrl . '/user/' . $this->legalAcceptedUser->id() . '/edit?pass-reset-token=', $current_url);
    $this->assertStringEndsWith('&check_logged_in=1', $current_url);

    // Check if resetting the password actually works:
    $newPassword = $this->randomMachineName();
    $page->fillField('edit-pass-pass1', $newPassword);
    $page->fillField('edit-pass-pass2', $newPassword);
    $page->pressButton('Save');

    $session->statusCodeEquals(200);
    $session->pageTextContains('The changes have been saved.');

    // Check, that the notice about the one-time login link is not displayed
    // anymore:
    $session->pageTextNotContains($message);

    // Check if the user's password has changed, note that we need to reload the
    // user object to get the updated password:
    User::load($this->legalAcceptedUser->id())->pass_raw;
  }

  /**
   * Test logging in with a user having already accepted the legal terms.
   */
  public function testPasswordResetWithoutLegalAccepted() {
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Reset the password by username via the password reset page.
    $this->drupalGet('user/password');
    $edit['name'] = $this->legalNotAcceptedUser->getAccountName();
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

    $parameters = [
      'query' => ['pass-reset-token' => ''],
      'absolute' => FALSE,
    ];

    $destination = $this->legalNotAcceptedUser->toUrl('edit-form', $parameters)->toString();

    $expected_query = [
      'destination' => $destination,
    ];

    $expected_url = Url::fromRoute('legal.legal_login', $expected_query)->setAbsolute()->toString();

    // Check user is redirected to T&C acceptance page.
    $this->assertStringStartsWith($expected_url, $this->getUrl());
    $session->statusCodeEquals(200);

    // Accept T&Cs and submit form.
    $edit = ['edit-legal-accept' => TRUE];
    $this->submitForm($edit, 'Confirm', 'legal-login');

    // Check that the notice about the one-time login link is displayed.
    $message = 'You have just used your one-time login link. It is no longer necessary to use this link to log in. It is recommended that you set your password.';
    $session->pageTextContainsOnce($message);

    // Check user is logged in.
    $account = User::load($this->legalNotAcceptedUser->id());
    $this->drupalUserIsLoggedIn($account);

    // Check user is redirected to their user page.
    $current_url = $this->getUrl();
    $expected_url = $this->baseUrl . '/user/' . $this->legalNotAcceptedUser->id() . '/edit?pass-reset-token=';
    $this->assertStringStartsWith($expected_url, $current_url);

    // Check if resetting the password actually works.
    $new_password = \Drupal::service('password_generator')->generate();
    $page->fillField('edit-pass-pass1', $new_password);
    $page->fillField('edit-pass-pass2', $new_password);
    $page->pressButton('Save');
    $session->statusCodeEquals(200);
    $session->pageTextContains('The changes have been saved.');

    // Check, that the notice about the one-time login link is not displayed.
    $session->pageTextNotContains($message);
  }

}
