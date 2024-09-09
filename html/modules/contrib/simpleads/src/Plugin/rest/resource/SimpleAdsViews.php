<?php

namespace Drupal\simpleads\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\simpleads\Model\Response;
use Drupal\simpleads\Model\SimpleAdsReference as SimpleAdsReferenceModel;

/**
 * Provides data for SimpleAds blocks.
 *
 * @RestResource(
 *   id = "simpleads_views",
 *   label = @Translation("SimpleAds Views"),
 *   uri_paths = {
 *     "canonical" = "/simpleads/views/{view_id}/{display_id}",
 *     "create" = "/simpleads/views/{view_id}/{display_id}"
 *   }
 * )
 */
class SimpleAdsViews extends ResourceBase {

  public function get($view_id, $display_id = NULL) {
    $response = new Response();
    $ids = [];

    $view_results = views_get_view_result($view_id, $display_id);

    if (empty($view_id)) {
      $response->setCode('invalid_view_id');
    }
    if (empty($display_id)) {
      $response->setCode('invalid_display_id');
    }
    else if (empty($view_results)) {
      $response->setCode('no_ads_found');
    }
    else {
      foreach ($view_results as $item) {
        $ids[] = $item->_entity->id();
      }
      $ads = new SimpleAdsReferenceModel();
      if ($items = $ads->load($ids)) {
        $response->setData($items->model())
          ->setCode('success');
      }
      else {
        $response->setCode('no_ads_found');
      }
    }

    $res = new ResourceResponse($response->model());
    $res->getCacheableMetadata()->addCacheContexts(['user.permissions', 'url.query_args']);
    $res->getCacheableMetadata()->addCacheTags(['simpleads_group']);
    return $res;
  }

}
