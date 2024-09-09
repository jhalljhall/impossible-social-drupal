<?php

namespace Drupal\simpleads\Plugin\rest\resource;

use Drupal\simpleads\Entity\Advertisement;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\simpleads\Model\Response;
use Drupal\simpleads\Model\SimpleAdsStats as SimpleAdsStatsModel;

/**
 * Provides data for SimpleAds statistics.
 *
 * @RestResource(
 *   id = "simpleads_stats",
 *   label = @Translation("SimpleAds Statistics"),
 *   uri_paths = {
 *     "canonical" = "/simpleads/stats/data/{entity_id}",
 *     "create" = "/simpleads/stats/data/{entity_id}"
 *   }
 * )
 */
class SimpleAdsStats extends ResourceBase {

  public function get($type = NULL, $entity_id = NULL) {
    $response = new Response();

    $ad = Advertisement::load($entity_id);

    if (empty($entity_id)) {
      $response->setCode('invalid_entity');
    }
    else if (!$ad) {
      $response->setCode('no_ads_found');
    }
    else {
      $stats = new SimpleAdsStatsModel();
      $response->setData($stats->setEntity($ad)->model())
        ->setCode('success');
    }

    $res = new ResourceResponse($response->model());
    $res->getCacheableMetadata()->addCacheContexts(['user.permissions', 'url.query_args']);
    $res->getCacheableMetadata()->addCacheTags(['simpleads_group']);

    if ($ad) {
      $res->addCacheableDependency($ad);
    }
    return $res;
  }

}
