<?php

namespace Drupal\Tests\commerce_webform_order\Functional\Element;

use Drupal\Tests\commerce_webform_order\Functional\CommerceWebformOrderTestBase;

/**
 * Tests Commerce Webform Order: Payment Method element.
 *
 * @group commerce_webform_order
 */
class PaymentMethodTest extends CommerceWebformOrderTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_payment_example',
    'commerce_webform_order_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $testWebforms = [
    'cwo_test_payment_method',
  ];

  /**
   * A test user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Enable the Payment process (Commerce Webform Order) checkout pane.
    /** @var \Drupal\commerce_checkout\Entity\CheckoutFlowInterface $checkout_flow */
    $checkout_flow = $this->entityTypeManager->getStorage('commerce_checkout_flow')->load('default');
    $checkout_flow_settings = $checkout_flow->get('configuration');
    $checkout_flow_settings['panes']['commerce_webform_order_payment_process']['step'] = 'payment';
    $checkout_flow->set('configuration', $checkout_flow_settings);
    $checkout_flow->save();

    $this->createEntity('commerce_payment_gateway', [
      'id' => 'payment_gateway_1',
      'label' => 'Payment Gateway #1',
      'plugin' => 'example_onsite',
      'configuration' => [
        'display_label' => 'Payment Gateway #1',
        'collect_billing_information' => FALSE,
      ],
    ]);

    $this->createEntity('commerce_payment_gateway', [
      'id' => 'payment_gateway_2',
      'label' => 'Payment Gateway #2',
      'plugin' => 'example_onsite',
      'configuration' => [
        'display_label' => 'Payment Gateway #2',
        'collect_billing_information' => FALSE,
      ],
    ]);

    $this->user = $this->createUser(['view commerce_product', 'access checkout']);
  }

  /**
   * Check payment method for anonymous users.
   */
  public function testPaymentMethod() {
    // Test as anonymous user.
    $this->drupalLogout();

    // Load the webform and get the submission form's submit path.
    $webform_storage = $this->entityTypeManager->getStorage('webform');
    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = $webform_storage->load('cwo_test_payment_method');
    $webform_path = $webform->getSetting('page_submit_path');

    $this->drupalGet($webform_path);

    $this->assertSession()->pageTextContains('Payment Gateway #1');
    $this->assertSession()->pageTextContains('Payment Gateway #2');

    $edit = [
      'product' => $this->productVariations['ONE']->id(),
      'payment_gateway_and_method[payment_gateway]' => 'new--credit_card--payment_gateway_2',
      'elements[payment_gateway_and_method][payment_method][payment_details][number]' => '4111111111111111',
      'elements[payment_gateway_and_method][payment_method][payment_details][expiration][month]' => '01',
      'elements[payment_gateway_and_method][payment_method][payment_details][expiration][year]' => (int) date('Y') + 1,
      'elements[payment_gateway_and_method][payment_method][payment_details][security_code]' => '111',
    ];
    $this->submitForm($edit, 'Submit');
    $this->completeCheckoutAndPayFromCheckout();

    $webform_submission = $this->getLastSubmission($webform);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $this->orderItemRepository->getLastByWebformSubmission($webform_submission, 'commerce_webform_order_handler')->getOrder();

    $this->assertEquals('payment_gateway_2', $order->get('payment_gateway')->target_id);
    $this->assertEquals('payment_gateway_2', $order->get('payment_method')->entity->getPaymentGatewayId());

    // Ensure the payment method wasn't stored.
    $this->drupalGet($webform_path);

    $this->assertSession()->pageTextContains('Payment Gateway #1');
    $this->assertSession()->pageTextContains('Payment Gateway #2');
    $this->assertSession()->pageTextNotContains('Visa ending in 1111');
  }

  /**
   * Check payment method for logged users.
   */
  public function testPaymentMethodLoggedUser() {
    $this->drupalLogin($this->user);

    // Load the webform and get the submission form's submit path.
    $webform_storage = $this->entityTypeManager->getStorage('webform');
    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = $webform_storage->load('cwo_test_payment_method');
    $webform_path = $webform->getSetting('page_submit_path');

    $this->drupalGet($webform_path);

    $this->assertSession()->pageTextContains('Payment Gateway #1');
    $this->assertSession()->pageTextContains('Payment Gateway #2');

    $edit = [
      'product' => $this->productVariations['ONE']->id(),
      'payment_gateway_and_method[payment_gateway]' => 'new--credit_card--payment_gateway_1',
      'elements[payment_gateway_and_method][payment_method][payment_details][number]' => '4111111111111111',
      'elements[payment_gateway_and_method][payment_method][payment_details][expiration][month]' => '01',
      'elements[payment_gateway_and_method][payment_method][payment_details][expiration][year]' => (int) date('Y') + 1,
      'elements[payment_gateway_and_method][payment_method][payment_details][security_code]' => '111',
    ];
    $this->submitForm($edit, 'Submit');
    $this->completeCheckoutAndPayFromCheckout(FALSE);

    $webform_submission = $this->getLastSubmission($webform);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order_1 = $this->orderItemRepository->getLastByWebformSubmission($webform_submission, 'commerce_webform_order_handler')->getOrder();

    $this->assertEquals('payment_gateway_1', $order_1->get('payment_gateway')->target_id);
    $this->assertEquals('payment_gateway_1', $order_1->get('payment_method')->entity->getPaymentGatewayId());

    // Ensure the payment method wasn't stored.
    $this->drupalGet($webform_path);

    $this->assertSession()->pageTextContains('Payment Gateway #1');
    $this->assertSession()->pageTextContains('Payment Gateway #2');
    $this->assertSession()->pageTextContains('Visa ending in 1111');

    // Ensure we can re-use a stored payment method.
    $edit = [
      'product' => $this->productVariations['ONE']->id(),
      'payment_gateway_and_method[payment_gateway]' => $order_1->get('payment_method')->target_id,
    ];
    $this->submitForm($edit, 'Submit');
    $this->completeCheckoutAndPayFromCheckout(FALSE);

    $webform_submission = $this->getLastSubmission($webform);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order_2 = $this->orderItemRepository->getLastByWebformSubmission($webform_submission, 'commerce_webform_order_handler')->getOrder();

    $this->assertEquals('payment_gateway_1', $order_2->get('payment_gateway')->target_id);
    $this->assertEquals($order_1->get('payment_method')->target_id, $order_2->get('payment_method')->target_id);
  }

}
