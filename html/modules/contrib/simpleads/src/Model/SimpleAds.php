<?php

namespace Drupal\simpleads\Model;

use Drupal\Core\Cache\Cache;

class SimpleAds extends SimpleAdsBase {

  /**
   * Load an individual ad.
   */
  public function load($entity) {
    if (!empty($entity)) {
      $this->setEntity($entity);
      return $this;
    }
  }

  /**
   * Load an individual ad by entity ID.
   */
  public function loadByEntityId($entity_id) {
    $entity = \Drupal::entityTypeManager()
      ->getStorage('simpleads')
      ->load($entity_id);
    $this->load($entity);
    return $this;
  }

  /**
   * Track ad clicks.
   */
  public function click() {
    if (!$this->isBotDetected() && $this->userAccess('count simpleads clicks')) {
      $this->checkCampaign();
      \Drupal::database()->insert('simpleads_clicks')
        ->fields([
          'entity_id'  => $this->getEntityId(),
          'timestamp'  => time(),
          'ip_address' => \Drupal::request()->getClientIp(),
        ])
        ->execute();
    }
    return $this;
  }

  /**
   * Track ad impressions.
   */
  public function impression() {
    if (!$this->isBotDetected() && $this->userAccess('count simpleads impressions')) {
      $this->checkCampaign();
      \Drupal::database()->insert('simpleads_impressions')
        ->fields([
          'entity_id'  => $this->getEntityId(),
          'timestamp'  => time(),
          'ip_address' => \Drupal::request()->getClientIp(),
        ])
        ->execute();
    }
    return $this;
  }

  public function checkCampaign() {
    if ($this->getEntityId()) {
      $ad = $this->getEntity();
      if ($campaign = $ad->getCampaign()) {
        // If campaign is done we need to make the ad inactive.
        if (!$campaign->isRunning($ad)) {
          $ad->setActive(FALSE);
          $ad->save();
          Cache::invalidateTags(['simpleads_group']);
        }
      }
    }
  }

}
