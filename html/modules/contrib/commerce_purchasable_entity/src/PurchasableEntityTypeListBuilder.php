<?php

namespace Drupal\commerce_purchasable_entity;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of purchasable entity types.
 *
 * @see \Drupal\commerce_purchasable_entity\Entity\PurchasableEntityType
 */
class PurchasableEntityTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['title'] = $this->t('Label');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['title'] = [
      'data' => $entity->label(),
      'class' => ['menu-label'],
    ];

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();

    $build['table']['#empty'] = $this->t(
      'No purchasable entity types available. <a href=":link">Add purchasable entity type</a>.',
      [':link' => Url::fromRoute('entity.commerce_purchasable_entity_type.add_form')->toString()]
    );

    return $build;
  }

}
