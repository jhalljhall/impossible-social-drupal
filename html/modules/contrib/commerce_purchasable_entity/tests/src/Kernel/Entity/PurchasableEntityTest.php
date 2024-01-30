<?php

namespace Drupal\Tests\commerce_purchasable_entity\Kernel\Entity;

use Drupal\commerce_price\Price;
use Drupal\commerce_purchasable_entity\Entity\PurchasableEntity;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;
use Drupal\user\UserInterface;

/**
 * Tests the Purchasable Entity.
 *
 * @coversDefaultClass \Drupal\commerce_purchasable_entity\Entity\PurchasableEntity
 *
 * @group commerce_purchasable_entity
 */
class PurchasableEntityTest extends CommerceKernelTestBase {

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

    $user = $this->createUser();
    $this->user = $this->reloadEntity($user);
  }

  /**
   * Test the Purchasable Entity.
   *
   * @covers ::getOrderItemTitle
   * @covers ::getOrderItemTypeId
   * @covers ::setSku
   * @covers ::getSku
   * @covers ::setTitle
   * @covers ::getTitle
   * @covers ::setPrice
   * @covers ::getPrice
   * @covers ::setPublished
   * @covers ::isPublished
   * @covers ::setCreatedTime
   * @covers ::getCreatedTime
   * @covers ::setOwner
   * @covers ::getOwner
   * @covers ::setOwnerId
   * @covers ::getOwnerId
   * @covers ::setStores
   * @covers ::getStores
   * @covers ::setStoreIds
   * @covers ::getStoreIds
   * @covers ::getCacheContexts
   */
  public function testPurchasableEntity() {
    $pe = PurchasableEntity::create([
      'type' => 'default',
      'title' => 'My Purchasable Entity Title',
      'sku' => '1000',
    ]);
    $pe->save();

    /** @var \Drupal\commerce_purchasable_entity\Entity\PurchasableEntityInterface $pe */
    $pe = $this->reloadEntity($pe);

    $this->assertEquals('My Purchasable Entity Title', $pe->getOrderItemTitle());
    $this->assertEquals('purchasable_entity', $pe->getOrderItemTypeId());
    $this->assertEquals('1000', $pe->getSku());

    $pe->setSku('1001');
    $this->assertEquals('1001', $pe->getSku());

    $pe->setTitle('My title');
    $this->assertEquals('My title', $pe->getTitle());

    $price = new Price(9.99, 'USD');
    $pe->setPrice($price);
    $this->assertEquals($price, $pe->getPrice());

    $pe->setPublished();
    $this->assertEquals(TRUE, $pe->isPublished());

    $pe->setCreatedTime(635879700);
    $this->assertEquals(635879700, $pe->getCreatedTime());

    $pe->setOwner($this->user);
    $this->assertEquals($this->user, $pe->getOwner());
    $this->assertEquals($this->user->id(), $pe->getOwnerId());
    $pe->setOwnerId(0);
    $this->assertInstanceOf(UserInterface::class, $pe->getOwner());
    $this->assertTrue($pe->getOwner()->isAnonymous());
    // Non-existent/deleted user ID.
    $pe->setOwnerId(892);
    $this->assertInstanceOf(UserInterface::class, $pe->getOwner());
    $this->assertTrue($pe->getOwner()->isAnonymous());
    $this->assertEquals(892, $pe->getOwnerId());
    $pe->setOwnerId($this->user->id());
    $this->assertEquals($this->user, $pe->getOwner());
    $this->assertEquals($this->user->id(), $pe->getOwnerId());

    $pe->setStores([$this->store]);
    $this->assertEquals([$this->store], $pe->getStores());
    $this->assertEquals([$this->store->id()], $pe->getStoreIds());
    $pe->setStores([]);
    $this->assertEquals([], $pe->getStores());
    $pe->setStoreIds([$this->store->id()]);
    $this->assertEquals([$this->store], $pe->getStores());
    $this->assertEquals([$this->store->id()], $pe->getStoreIds());

    $this->assertEquals([
      'store',
    ], $pe->getCacheContexts());
  }

}
