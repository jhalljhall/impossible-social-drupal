<?php

namespace Drupal\simpleads\Model;

use Drupal\node\Entity\Node;

class SimpleAdsGroups extends SimpleAdsGroupsBase {

  /**
   * Load all ads.
   */
  public function load() {
    // Make sure only active ads getting displayed.
    $properties = [
      'group'    => $this->getEntityId(),
      'status'   => TRUE,
      'inactive' => FALSE,
    ];
    $moduleHandler = \Drupal::moduleHandler();
    // Adding support for Domain Access (Domain) module and Domain Entity.
    if ($moduleHandler->moduleExists('domain_access') && $moduleHandler->moduleExists('domain_entity')) {
      $active_domain_id = \Drupal::service('domain.negotiator')->getActiveId();
      $properties['domain_access'] = $active_domain_id;
    }
    
    \Drupal::moduleHandler()->alter('simpleads_group_properties', $properties);

    $entities = \Drupal::entityTypeManager()
      ->getStorage('simpleads')
      ->loadByProperties($properties);

    $current_node = NULL;
    $node_ref_ids = [];
    if ($current_node_id = $this->getCurrentNode()) {
      if ($current_node = Node::load($current_node_id)) {
        if ($node_ref_field = $this->getNodeRef()) {
          if ($current_node->hasField($node_ref_field)) {
            foreach ($current_node->get($node_ref_field)->getValue() as $item) {
              $node_ref_ids[] = $item['target_id'];
            }
          }
        }
      }
    }

    $ads = [];
    foreach ($entities as $entity) {
      $simpleads_ref_ids = [];
      if ($simpleads_ref_field = $this->getSimpleAdsRef()) {
        if ($entity->hasField($simpleads_ref_field)) {
          foreach ($entity->get($simpleads_ref_field)->getValue() as $item) {
            $simpleads_ref_ids[] = $item['target_id'];
          }
        }
      }
      if ($ad = (new SimpleAds())->load($entity)) {
        if (!empty($node_ref_ids) && !empty($simpleads_ref_ids)) {
          foreach ($simpleads_ref_ids as $id) {
            if (in_array($id, $node_ref_ids)) {
              $ads[] = $ad->model();
            }
          }
        }
        else {
          $ads[] = $ad->model();
        }
      }
    }
    if (empty($ads)) return NULL;
    $this->setAds($ads);
    return $this;
  }

}
