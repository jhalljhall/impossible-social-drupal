<?php

namespace Drupal\Tests\commerce_purchasable_entity\Kernel;

use Drupal\commerce_purchasable_entity\Entity\PurchasableEntity;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;

/**
 * Tests the purchasable entity in a multilingual context.
 *
 * @group commerce_purchasable_entity
 */
class PurchasableEntityMultilingualTest extends CommerceKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'commerce_purchasable_entity',
    'language',
    'content_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('commerce_purchasable_entity');
    $this->installConfig(['commerce_purchasable_entity']);
    ConfigurableLanguage::createFromLangcode('fr')->save();
    ConfigurableLanguage::createFromLangcode('sr')->save();

    $user = $this->createUser([], ['administer commerce_purchasable_entity_type']);
    $this->container->get('current_user')->setAccount($user);
  }

  /**
   * Tests that the purchasable entity's stores are translated.
   */
  public function testPurchasableEntityStoresTranslated() {
    $this->container->get('content_translation.manager')
      ->setEnabled('commerce_store', 'online', TRUE);
    $this->store = $this->reloadEntity($this->store);
    $this->store->addTranslation('fr', [
      'name' => 'Magasin par défaut',
    ])->save();

    $this->container->get('content_translation.manager')
      ->setEnabled('commerce_purchasable_entity', 'default', TRUE);

    $pe = PurchasableEntity::create([
      'type' => 'default',
      'title' => 'My Super Purchasable Entity',
      'stores' => [$this->store],
    ]);
    $pe->addTranslation('fr', [
      'title' => 'Mon super Purchasable Entity',
    ]);
    $pe->addTranslation('sr', [
      'title' => 'Мој супер Purchasable Entity',
    ]);

    $stores = $pe->getStores();
    $this->assertEquals('Default store', reset($stores)->label());

    $stores = $pe->getTranslation('fr')->getStores();
    $this->assertEquals('Magasin par défaut', reset($stores)->label());

    $stores = $pe->getTranslation('en')->getStores();
    $this->assertEquals('Default store', reset($stores)->label());

    $stores = $pe->getTranslation('sr')->getStores();
    $this->assertEquals('Default store', reset($stores)->label());
  }

}
