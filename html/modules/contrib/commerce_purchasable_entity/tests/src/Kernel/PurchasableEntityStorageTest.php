<?php

namespace Drupal\Tests\commerce_purchasable_entity\Kernel;

use Drupal\commerce_purchasable_entity\Entity\PurchasableEntity;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;

/**
 * Tests the purchasable entity storage.
 *
 * @group commerce_purchasable_entity
 */
class PurchasableEntityStorageTest extends CommerceKernelTestBase {

  /**
   * The purchasable entity storage.
   *
   * @var \Drupal\commerce_purchasable_entity\PurchasableEntityStorageInterface
   */
  protected $purchasableEntityStorage;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'commerce_purchasable_entity',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_purchasable_entity');
    $this->installConfig(['commerce_purchasable_entity']);

    $this->purchasableEntityStorage = $this->container->get('entity_type.manager')->getStorage('commerce_purchasable_entity');

    $user = $this->createUser([], ['administer commerce_purchasable_entity_type']);
    $this->container->get('current_user')->setAccount($user);
  }

  /**
   * Tests loading purchasable entities by SKU.
   */
  public function testLoadBySku() {
    $sku = strtolower($this->randomMachineName());
    $pe = PurchasableEntity::create([
      'type' => 'default',
      'sku' => $sku,
      'title' => $this->randomString(),
    ]);
    $pe->save();

    $result = $this->purchasableEntityStorage->loadBySku('FAKE');
    $this->assertNull($result);

    $result = $this->purchasableEntityStorage->loadBySku($sku);
    $this->assertEquals($result->id(), $pe->id());
  }

}
