<?php

namespace Drupal\simpleads\Plugin\rest\resource;

use Drupal\Core\Cache\Cache;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\simpleads\Model\Response;
use Drupal\simpleads\Model\SimpleAds as SimpleAdsModel;

/**
 * Track SimpleAds impressions.
 *
 * @RestResource(
 *   id = "simpleads_impression",
 *   label = @Translation("SimpleAds Impression"),
 *   uri_paths = {
 *     "canonical" = "/simpleads/impression/{entity_id}",
 *     "create" = "/simpleads/impression/{entity_id}"
 *   }
 * )
 */
class SimpleAdsImpressions extends ResourceBase {

  public function post($entity_id = NULL) {
    $response = new Response();

    $ad = new SimpleAdsModel();
    $ad->loadByEntityId($entity_id);

    if (empty($ad->getEntityId())) {
      $response->setCode('invalid_entity');
    }
    else {
      $ad->impression();
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
