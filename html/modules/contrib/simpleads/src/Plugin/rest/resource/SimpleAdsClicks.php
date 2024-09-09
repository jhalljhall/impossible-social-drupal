<?php

namespace Drupal\simpleads\Plugin\rest\resource;

use Drupal\Core\Cache\Cache;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\simpleads\Model\Response;
use Drupal\simpleads\Model\SimpleAds as SimpleAdsModel;

/**
 * Track SimpleAds clicks.
 *
 * @RestResource(
 *   id = "simpleads_click",
 *   label = @Translation("SimpleAds Click"),
 *   uri_paths = {
 *     "canonical" = "/simpleads/click/{entity_id}",
 *     "create" = "/simpleads/click/{entity_id}"
 *   }
 * )
 */
class SimpleAdsClicks extends ResourceBase {

  public function post($entity_id = NULL) {
    $response = new Response();

    $ad = new SimpleAdsModel();
    $ad->loadByEntityId($entity_id);

    if (empty($ad->getEntityId())) {
      $response->setCode('invalid_entity');
    }
    else {
      $ad->click();
      $response->setData($ad->model())
        ->setCode('success');
    }

    $res = new ResourceResponse($response->model());
    if ($entity = $ad->getEntity()) {
      Cache::invalidateTags($entity->getCacheTags());
      $res->addCacheableDependency($entity);
    }
    return $res;
  }

}
