<?php

namespace Drupal\Tests\commerce_purchasable_entity\Kernel;

use Drupal\commerce_purchasable_entity\Entity\PurchasableEntity;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;

/**
 * Tests the purchasable entity access control.
 *
 * @coversDefaultClass \Drupal\commerce_purchasable_entity\PurchasableEntityAccessControlHandler
 * @group commerce_purchasable_entity
 */
class PurchasableEntityAccessTest extends CommerceKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'commerce_purchasable_entity',
  ];

  /**
   * A sample user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_purchasable_entity');
    $this->installConfig(['commerce_purchasable_entity']);

    // Create uid: 1 here so that it's skipped in test cases.
    $this->createUser();
    $regular_user = $this->createUser(['uid' => 2]);
    \Drupal::currentUser()->setAccount($regular_user);
  }

  /**
   * @covers ::checkAccess
   */
  public function testAccess() {
    /** @var \Drupal\commerce_purchasable_entity\Entity\PurchasableEntityInterface $pe */
    $pe = PurchasableEntity::create([
      'type' => 'default',
      'sku' => $this->randomMachineName(),
      'title' => $this->randomString(),
      'status' => 1,
    ]);
    $pe->save();
    $pe = $this->reloadEntity($pe);

    $account = $this->createUser([], ['access administration pages']);
    $this->assertFalse($pe->access('view', $account));
    $this->assertFalse($pe->access('update', $account));
    $this->assertFalse($pe->access('delete', $account));

    $account = $this->createUser([], ['administer commerce_purchasable_entity_type']);
    $this->assertTrue($pe->access('view', $account));
    $this->assertTrue($pe->access('update', $account));
    $this->assertTrue($pe->access('delete', $account));

    $account = $this->createUser([], ['view commerce_purchasable_entity']);
    $this->assertTrue($pe->access('view', $account));
    $this->assertFalse($pe->access('update', $account));
    $this->assertFalse($pe->access('delete', $account));

    $account = $this->createUser([], ['edit commerce_purchasable_entity']);
    $this->assertFalse($pe->access('view', $account));
    $this->assertTrue($pe->access('update', $account));
    $this->assertFalse($pe->access('delete', $account));

    $account = $this->createUser([], ['delete commerce_purchasable_entity']);
    $this->assertFalse($pe->access('view', $account));
    $this->assertFalse($pe->access('update', $account));
    $this->assertTrue($pe->access('delete', $account));
  }

  /**
   * @covers ::checkCreateAccess
   */
  public function testCreateAccess() {
    $access_control_handler = \Drupal::entityTypeManager()->getAccessControlHandler('commerce_purchasable_entity');

    $account = $this->createUser([], ['access content']);
    $this->assertFalse($access_control_handler->createAccess('default', $account));

    $account = $this->createUser([], ['create commerce_purchasable_entity']);
    $this->assertTrue($access_control_handler->createAccess('default', $account));
  }

  /**
   * Tests route access for variations.
   */
  public function testRouteAccess() {
    /** @var \Drupal\commerce_purchasable_entity\Entity\PurchasableEntityInterface $pe */
    $pe = PurchasableEntity::create([
      'type' => 'default',
      'sku' => $this->randomMachineName(),
      'title' => $this->randomString(),
      'status' => 1,
    ]);
    $pe->save();
    $pe = $this->reloadEntity($pe);

    $account = $this->createUser([], ['administer commerce_purchasable_entity_type']);
    $this->assertTrue($pe->toUrl('collection')->access($account));
    $this->assertTrue($pe->toUrl('canonical')->access($account));
    $this->assertTrue($pe->toUrl('add-page')->access($account));
    $this->assertTrue($pe->toUrl('add-form')->access($account));
    $this->assertTrue($pe->toUrl('edit-form')->access($account));
    $this->assertTrue($pe->toUrl('delete-form')->access($account));

    $account = $this->createUser([], ['view commerce_purchasable_entity']);
    $this->assertFalse($pe->toUrl('collection')->access($account));
    $this->assertFalse($pe->toUrl('canonical')->access($account));
    $this->assertFalse($pe->toUrl('add-page')->access($account));
    $this->assertFalse($pe->toUrl('add-form')->access($account));
    $this->assertFalse($pe->toUrl('edit-form')->access($account));
    $this->assertFalse($pe->toUrl('delete-form')->access($account));

    $account = $this->createUser([], ['edit commerce_purchasable_entity']);
    $this->assertFalse($pe->toUrl('collection')->access($account));
    $this->assertTrue($pe->toUrl('canonical')->access($account));
    $this->assertFalse($pe->toUrl('add-page')->access($account));
    $this->assertFalse($pe->toUrl('add-form')->access($account));
    $this->assertTrue($pe->toUrl('edit-form')->access($account));
    $this->assertFalse($pe->toUrl('delete-form')->access($account));

    $account = $this->createUser([], ['delete commerce_purchasable_entity']);
    $this->assertFalse($pe->toUrl('collection')->access($account));
    $this->assertFalse($pe->toUrl('canonical')->access($account));
    $this->assertFalse($pe->toUrl('add-page')->access($account));
    $this->assertFalse($pe->toUrl('add-form')->access($account));
    $this->assertFalse($pe->toUrl('edit-form')->access($account));
    $this->assertTrue($pe->toUrl('delete-form')->access($account));
  }

}
