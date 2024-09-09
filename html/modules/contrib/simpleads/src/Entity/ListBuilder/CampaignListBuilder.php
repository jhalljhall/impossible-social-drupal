<?php

namespace Drupal\simpleads\Entity\ListBuilder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\Component\Serialization\Json;

/**
 * Defines a class to build a listing of Campaign entities.
 *
 * @ingroup simpleads
 */
class CampaignListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Campaign ID');
    $header['name'] = $this->t('Name');
    $header['type'] = $this->t('Type');
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
    /* @var $entity \Drupal\simpleads\Entity\Campaign */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.simpleads_campaign.edit_form',
      ['simpleads_campaign' => $entity->id()]
    );
    $row['type'] = ($types = $entity->getType()) ? join(",", $this->getTypeName($types)) : '';
    return $row + parent::buildRow($entity);
  }

  /**
   * Get campaign type name.
   */
  protected function getTypeName($types) {
    $all_types = simpleads_campaign_types();
    $names = [];
    if (is_array($types)) {
      foreach ($types as $type) {
        $names[] = !empty($all_types[$type]) ? $all_types[$type] : $type;
      }
    }
    else {
      $names[] = !empty($all_types[$types]) ? $all_types[$types] : $types;
    }
    return $names;
  }

}
