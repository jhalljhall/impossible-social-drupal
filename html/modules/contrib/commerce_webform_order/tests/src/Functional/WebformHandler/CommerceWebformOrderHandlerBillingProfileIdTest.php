<?php

namespace Drupal\Tests\commerce_webform_order\Functional\WebformHandler;

use Drupal\profile\Entity\Profile;
use Drupal\Tests\commerce_webform_order\Functional\CommerceWebformOrderTestBase;

/**
 * Tests Commerce Webform Order handler: Billing Profile.
 *
 * @group commerce_webform_order
 */
class CommerceWebformOrderHandlerBillingProfileIdTest extends CommerceWebformOrderTestBase {

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
    'cwo_test_billing_profile_id_1',
    'cwo_test_billing_profile_id_2',
  ];

  /**
   * The billing profile.
   *
   * @var \Drupal\profile\Entity\ProfileInterface
   */
  protected $billingProfile;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->billingProfile = Profile::create([
      'type' => 'customer',
      'profile_id' => 1,
    ]);
    $this->billingProfile->save();
  }

  /**
   * Existing billing profile.
   *
   * In this test we are going to check the owner ID feature for current user:
   *   - After submit, there current user is the order's owner.
   */
  public function testOneBillingProfileId() {
    $webform_submission = $this->postProductVariationToWebform('ONE', 'cwo_test_billing_profile_id_1');
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $this->orderItemRepository->getLastByWebformSubmission($webform_submission, 'commerce_webform_order_handler');
    $order = $order_item->getOrder();

    // Billing profile ID is the order's billing profile ID.
    $this->assertEquals($this->billingProfile->id(), $order->getBillingProfile()->id());
  }

  /**
   * Empty billing profile.
   *
   * In this test we are going to check the owner ID feature for a specific
   * user:
   *   - After submit, there anonymous user is the order's owner.
   */
  public function testNullBillingProfileId() {
    $webform_submission = $this->postProductVariationToWebform('ONE', 'cwo_test_billing_profile_id_2');
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $this->orderItemRepository->getLastByWebformSubmission($webform_submission, 'commerce_webform_order_handler');
    $order = $order_item->getOrder();

    // Null is the order's billing profile ID.
    $this->assertNull($order->getBillingProfile());
  }

}
