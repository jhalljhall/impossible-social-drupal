<?php

namespace Drupal\Tests\commerce_webform_order\Functional\WebformHandler;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\commerce_webform_order\Functional\CommerceWebformOrderTestBase;

/**
 * Tests Commerce Webform Order handler: Prevent update.
 *
 * @group commerce_webform_order
 */
class CommerceWebformOrderHandlerPreventUpdateTest extends CommerceWebformOrderTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_webform_order_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $testWebforms = [
    'cwo_test_prevent_update_1',
    'cwo_test_prevent_update_2',
  ];

  /**
   * Prevent update is disabled.
   *
   * In this test we are going to check the prevent update feature when it is
   * disabled:
   *   - User can update a submission when the associated order is completed.
   */
  public function testPreventUpdateDisabled() {
    // Test as anonymous user.
    $this->drupalLogout();

    $webform_submission = $this->postProductVariationToWebform('ONE', 'cwo_test_prevent_update_1');

    // Go to the cart page and complete the checkout.
    $this->gotToCartPageFromLink();
    $this->completeCheckoutFromCart();

    // We can update the webform submission without problems.
    $this->resetCache();
    $options = ['query' => ['token' => $webform_submission->getToken()]];
    $this->drupalGet($webform_submission->getWebform()->getSetting('page_submit_path'), $options);
    $this->assertSession()->statusCodeEquals(200);

  }

  /**
   * Prevent update is enabled.
   *
   * In this test we are going to check the prevent update feature when it is
   * enabled:
   *   - User can't update a submission when the associated order is completed.
   */
  public function testPreventUpdateEnabled() {
    // Test as anonymous user.
    $this->drupalLogout();

    $webform_submission = $this->postProductVariationToWebform('ONE', 'cwo_test_prevent_update_2');

    // Go to the cart page and complete the checkout.
    $this->gotToCartPageFromLink();
    $this->completeCheckoutFromCart();

    // We get a 403 error response code trying to update the webform submission.
    $this->resetCache();
    $options = ['query' => ['token' => $webform_submission->getToken()]];
    $this->drupalGet($webform_submission->getWebform()->getSetting('page_submit_path'), $options);
    $this->assertSession()->statusCodeEquals(403);
  }

}
