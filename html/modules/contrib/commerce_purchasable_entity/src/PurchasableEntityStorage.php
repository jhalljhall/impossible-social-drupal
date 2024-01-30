<?php

namespace Drupal\commerce_purchasable_entity;

use Drupal\commerce\CommerceContentEntityStorage;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the purchasable entity storage.
 */
class PurchasableEntityStorage extends CommerceContentEntityStorage implements PurchasableEntityStorageInterface {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->requestStack = $container->get('request_stack');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function loadBySku($sku) {
    $entities = $this->loadByProperties(['sku' => $sku]);
    $entity = reset($entities);

    return $entity ?: NULL;
  }

}
