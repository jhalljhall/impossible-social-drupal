<?php

namespace Drupal\simpleads\Model;

class SimpleAdsReference extends SimpleAdsGroupsBase {

  /**
   * Load all ads.
   */
  public function load($ids) {
    if (empty($ids)) return NULL;
    // Make sure only active ads getting displayed.
    $properties = [
      'status'   => TRUE,
      'inactive' => FALSE,
    ];
    $moduleHandler = \Drupal::moduleHandler();
    // Adding support for Domain Access (Domain) module and Domain Entity.
    if ($moduleHandler->moduleExists('domain_access') && $moduleHandler->moduleExists('domain_entity')) {
      $active_domain_id = \Drupal::service('domain.negotiator')->getActiveId();
      $properties['domain_access'] = $active_domain_id;
    }
    $entities = \Drupal::entityTypeManager()
      ->getStorage('simpleads')
      ->loadByProperties($properties);

    $ads = [];
    foreach ($entities as $entity) {
      if ($ad = (new SimpleAds())->load($entity)) {
        if (in_array($entity->id(), $ids)) {
          $ads[] = $ad->model();
        }
      }
    }
    if (empty($ads)) return NULL;
    $this->setAds($ads);
    return $this;
  }

}
