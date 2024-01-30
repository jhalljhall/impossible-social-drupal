<?php

namespace Drupal\Tests\commerce_webform_order\Functional;

use Drupal\Tests\commerce\Functional\CommerceBrowserTestBase;
use Drupal\Tests\webform\Traits\WebformBrowserTestTrait;

/**
 * Provides a base class for Commerce functional tests.
 */
abstract class CommerceWebformOrderTestBase extends CommerceBrowserTestBase {

  use WebformBrowserTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The order item repository.
   *
   * @var \Drupal\commerce_webform_order\OrderItemRepositoryInterface
   */
  protected $orderItemRepository;

  /**
   * The product.
   *
   * @var \Drupal\commerce_product\Entity\ProductInterface
   */
  protected $product;

  /**
   * The product variations.
   *
   * @var \Drupal\commerce_product\Entity\ProductVariationInterface[]
   */
  protected $productVariations = [];

  /**
   * Webforms to load.
   *
   * @var array
   */
  protected static $testWebforms = [];

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'commerce_product',
    'commerce_order',
    'commerce_cart',
    'commerce_checkout',
    'commerce_payment',
    'commerce_product',
    'commerce_purchasable_entity',
    'views_ui',
    'webform',
  ];

  /**
   * {@inheritdoc}
   */
  public function tearDown(): void {
    $this->purgeSubmissions();

    parent::tearDown();
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->container->get('entity_type.manager');

    $this->loadWebforms(static::$testWebforms);

    $this->placeBlock('commerce_cart');
    $this->placeBlock('commerce_checkout_progress');

    $this->orderItemRepository = $this->container->get('commerce_webform_order.order_item_repository');

    // Disable some checkout panes to facilitate the testing process.
    /** @var \Drupal\commerce_checkout\Entity\CheckoutFlowInterface $checkout_flow */
    $checkout_flow = $this->entityTypeManager->getStorage('commerce_checkout_flow')->load('default');
    $checkout_flow_settings = $checkout_flow->get('configuration');
    $checkout_flow_settings['panes']['payment_information']['step'] = '_disabled';
    $checkout_flow_settings['panes']['payment_process']['step'] = '_disabled';
    $checkout_flow_settings['panes']['commerce_webform_order_payment_process']['step'] = '_disabled';
    $checkout_flow->set('configuration', $checkout_flow_settings);
    $checkout_flow->save();

    $this->productVariations['ONE'] = $this->createEntity('commerce_product_variation', [
      'type' => 'default',
      'sku' => 'ONE',
      'title' => 'Product variation #1',
      'price' => [
        'number' => 5.00,
        'currency_code' => 'USD',
      ],
    ]);
    $this->productVariations['TWO'] = $this->createEntity('commerce_product_variation', [
      'type' => 'default',
      'sku' => 'TWO',
      'title' => 'Product variation #2',
      'price' => [
        'number' => 7.00,
        'currency_code' => 'USD',
      ],
    ]);
    $this->productVariations['THREE'] = $this->createEntity('commerce_product_variation', [
      'type' => 'default',
      'sku' => 'THREE',
      'title' => 'Product variation #3',
      'price' => [
        'number' => 12.00,
        'currency_code' => 'USD',
      ],
    ]);

    $this->product = $this->createEntity('commerce_product', [
      'type' => 'default',
      'title' => 'My product',
      'variations' => $this->productVariations,
      'stores' => [$this->store],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'administer commerce_checkout_flow',
      'administer views',
      'administer webform',
      'administer webform submission',
    ], parent::getAdministratorPermissions());
  }

  /**
   * Reset caches.
   */
  protected function resetCache() {
    $this->entityTypeManager->getStorage('webform_submission')->resetCache();
    $this->entityTypeManager->getStorage('commerce_order')->resetCache();
    $this->entityTypeManager->getStorage('commerce_order_item')->resetCache();
  }

  /**
   * Post a product variation by SKU to a webform by ID.
   *
   * @param string $product_sku
   *   The product variation SKU.
   * @param string $webform_id
   *   The webform ID.
   * @param string $submit
   *   The id, name, label or value of the submit button which is to be clicked.
   * @param array $options
   *   Options to be forwarded to the url generator.
   *
   * @return \Drupal\webform\WebformSubmissionInterface|null
   *   The current webform submission.
   */
  protected function postProductVariationToWebform($product_sku, $webform_id, $submit = 'Submit', array $options = []) {
    // Post the selected product variation, load the last submission and return
    // it.
    $values = ['product' => $this->productVariations[$product_sku]->id()];

    return $this->postValuesToWebform($values, $webform_id, $submit, $options);
  }

  /**
   * Post a product variation by SKU to a webform by ID.
   *
   * @param array $values
   *   The webform submission values.
   * @param string $webform_id
   *   The webform ID.
   * @param string $submit
   *   The id, name, label or value of the submit button which is to be clicked.
   * @param array $options
   *   Options to be forwarded to the url generator.
   *
   * @return \Drupal\webform\WebformSubmissionInterface|null
   *   The current webform submission.
   */
  protected function postValuesToWebform(array $values, $webform_id, $submit = 'Submit', array $options = []) {
    // Load the webform and get the submission form's submit path.
    $webform_storage = $this->entityTypeManager->getStorage('webform');
    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = $webform_storage->load($webform_id);
    $webform_path = $webform->getSetting('page_submit_path');
    $this->drupalGet($webform_path, $options);

    // Post the values, load the last submission and return it.
    $this->submitForm($values, $submit);
    $sid = $this->getLastSubmissionId($webform);

    $submission_storage = $this->entityTypeManager->getStorage('webform_submission');
    return $submission_storage->load($sid);
  }

  /**
   * Go to the cart page from add to cart message link.
   */
  protected function gotToCartPageFromLink() {
    // @see \Drupal\commerce_cart\EventSubscriber\CartEventSubscriber::displayAddToCartMessage()
    $cart_link = $this->getSession()->getPage()->findLink('your cart');
    $cart_link->click();
  }

  /**
   * Complete a cart's checkout from the cart page.
   */
  protected function completeCheckoutFromCart() {
    // Submit the cart's form.
    $this->submitForm([], 'Checkout');

    // We are in the login checkout step, continue.
    $this->submitForm([], 'Continue as Guest');

    // We are in order the information checkout step, continue.
    $this->submitForm([
      'contact_information[email]' => 'guest@example.com',
      'contact_information[email_confirm]' => 'guest@example.com',
    ], 'Continue to review');

    // We are in the review checkout step, complete the order.
    $this->submitForm([], 'Complete checkout');
  }

  /**
   * Complete a cart's checkout from the checkout page.
   *
   * @param bool $anonymous
   *   TRUE if the customer is anonymous.
   */
  protected function completeCheckoutAndPayFromCheckout($anonymous = TRUE) {
    // We are in the login checkout step, continue.
    if ($anonymous) {
      $this->submitForm([], 'Continue as Guest');

      // We are in order the information checkout step, continue.
      $this->submitForm([
        'contact_information[email]' => 'guest@example.com',
        'contact_information[email_confirm]' => 'guest@example.com',
      ], 'Continue to review');
    }

    // We are in the review checkout step, complete the order.
    $this->submitForm([], 'Pay and complete purchase');
  }

}
