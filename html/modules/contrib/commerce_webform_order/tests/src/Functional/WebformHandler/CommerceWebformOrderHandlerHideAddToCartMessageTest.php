<?php

namespace Drupal\Tests\commerce_webform_order\Functional\WebformHandler;

use Drupal\Tests\commerce_webform_order\Functional\CommerceWebformOrderTestBase;

/**
 * Tests Commerce Webform Order handler: Hide add to cart message.
 *
 * @group commerce_webform_order
 */
class CommerceWebformOrderHandlerHideAddToCartMessageTest extends CommerceWebformOrderTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_webform_order_test_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $testWebforms = [
    'cwo_test_hide_cart_message_1',
    'cwo_test_hide_cart_message_2',
  ];

  /**
   * Hide add to cart message is disabled.
   *
   * In this test we are going to check the debug feature when it is disabled:
   *   - After submit, the add to cart message is displayed.
   */
  public function testHideAddToCartMessageDisabled() {
    // Test as anonymous user.
    $this->drupalLogout();

    $this->postProductVariationToWebform('ONE', 'cwo_test_hide_cart_message_1');
    // A status message is displayed with the cart message.
    $this->assertSession()->pageTextContains('added to your cart');
  }

  /**
   * Hide add to cart message is disabled on translated page.
   *
   * In this test we are going to check the debug feature when it is disabled:
   *   - After submit, the add to cart message is displayed.
   */
  public function testHideAddToCartMessageDisabledTranslated() {
    // Test as anonymous user.
    $this->drupalLogout();

    // Load the webform and get the submission form's submit path.
    $webform_storage = $this->entityTypeManager->getStorage('webform');
    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = $webform_storage->load('cwo_test_hide_cart_message_1');
    $webform_path = $webform->getSetting('page_submit_path');

    // Check in Spanish version.
    $this->drupalGet('/es' . $webform_path);
    $edit = ['product' => $this->productVariations['ONE']->id()];
    $this->submitForm($edit, 'Submit');
    // A status message is displayed with the cart message.
    $this->assertSession()->pageTextNotContains('added to your cart');
    $this->assertSession()->pageTextContains('añadido a su carrito');
  }

  /**
   * Hide add to cart message is enabled.
   *
   * In this test we are going to check the debug feature when it is enabled:
   *   - After submit, the add to cart message isn't displayed.
   */
  public function testAddToCartMessageEnabled() {
    // Test as anonymous user.
    $this->drupalLogout();

    $this->postProductVariationToWebform('ONE', 'cwo_test_hide_cart_message_2');
    // A status message isn't displayed with the cart message.
    $this->assertSession()->pageTextNotContains('added to your cart');
  }

  /**
   * Hide add to cart message is enabled on translated page.
   *
   * In this test we are going to check the debug feature when it is enabled:
   *   - After submit, the add to cart message isn't displayed.
   */
  public function testAddToCartMessageEnabledTranslated() {
    // Test as anonymous user.
    $this->drupalLogout();

    // Check in Spanish version.
    // Load the webform and get the submission form's submit path.
    $webform_storage = $this->entityTypeManager->getStorage('webform');
    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = $webform_storage->load('cwo_test_hide_cart_message_2');
    $webform_path = $webform->getSetting('page_submit_path');

    $this->drupalGet('/es' . $webform_path);
    $edit = ['product' => $this->productVariations['ONE']->id()];
    $this->submitForm($edit, 'Submit');
    // A status message is displayed with the cart message.
    $this->assertSession()->pageTextNotContains('added to your cart');
    $this->assertSession()->pageTextNotContains('añadido a su carrito');
  }

}
