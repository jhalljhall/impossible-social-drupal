<?php

namespace Drupal\simpleads\Entity\ListBuilder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\Component\Serialization\Json;

/**
 * Defines a class to build a listing of Advertisement entities.
 *
 * @ingroup simpleads
 */
class AdvertisementListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Advertisement ID');
    $header['type'] = $this->t('Type');
    $header['group'] = $this->t('Group');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity) {
    $operations = parent::getOperations($entity);
    $ajax_attributes = [
      'class' => ['use-ajax'],
      'data-dialog-type' => 'modal',
      'data-dialog-options' => Json::encode(['width' => 700]),
    ];
    if ($entity->access('update') && $entity->hasLinkTemplate('statistics')) {
      $operations['stats'] = [
        'title' => $this->t('Statistics'),
        'weight' => -10,
        'url' => $this->ensureDestination($entity->toUrl('statistics')),
      ];
    }
    if (!empty($operations['delete'])) {
      $operations['delete']['attributes'] = $ajax_attributes;
    }
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\simpleads\Entity\Advertisement */
    $row['id'] = $entity->id();
    $row['type'] = $entity->getType();
    $row['group'] = $entity->getGroup();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.simpleads.edit_form',
      ['simpleads' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
