<?php

namespace Drupal\simpleads\Model;

class SimpleAdsStats extends SimpleAdsStatsBase {

  public function getStats() {
    if ($entity_id = $this->getEntity()->id()) {
      $ad = \Drupal::service('simpleads.stats')
        ->setEntityId($entity_id);
      return [
        'today' => $ad->loadTodayData(),
        'all'   => $ad->loadData(),
      ];
    }
    return [];
  }

}
