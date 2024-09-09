<?php

namespace Drupal\simpleads\Entity\ListBuilder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\Component\Serialization\Json;

/**
 * Defines a class to build a listing of Group entities.
 *
 * @ingroup simpleads
 */
class GroupListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Group ID');
    $header['name'] = $this->t('Name');
    $header['description'] = $this->t('Description');
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
    if (!empty($operations['edit'])) {
      $operations['edit']['attributes'] = $ajax_attributes;
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
    /* @var $entity \Drupal\simpleads\Entity\Group */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.simpleads_group.edit_form',
      ['simpleads_group' => $entity->id()]
    );
    $row['description'] = $entity->getDescription();
    return $row + parent::buildRow($entity);
  }

}
