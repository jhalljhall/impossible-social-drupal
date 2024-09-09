<?php

namespace Drupal\simpleads\Plugin\rest\resource;

use Drupal\simpleads\Entity\Group;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\simpleads\Model\Response;
use Drupal\simpleads\Model\SimpleAdsGroups as SimpleAdsGroupsModel;

/**
 * Provides data for SimpleAds blocks.
 *
 * @RestResource(
 *   id = "simpleads_group",
 *   label = @Translation("SimpleAds Group"),
 *   uri_paths = {
 *     "canonical" = "/simpleads/group/{entity_id}/{current_node_id}/{node_ref_field}/{simpleads_ref_field}",
 *     "create" = "/simpleads/group/{entity_id}/{current_node_id}/{entity_id}/{node_ref_field}/{simpleads_ref_field}"
 *   }
 * )
 */
class SimpleAdsGroup extends ResourceBase {

  public function get($entity_id = NULL, $current_node_id = NULL, $node_ref_field = NULL, $simpleads_ref_field = NULL) {
    $response = new Response();

    $group = Group::load($entity_id);

    if (empty($entity_id)) {
      $response->setCode('invalid_group');
    }
    else if (!$group) {
      $response->setCode('group_not_found');
    }
    else {
      $ads = new SimpleAdsGroupsModel();
      if ($items = $ads->setEntityId($entity_id)
          ->setCurrentNode($current_node_id)
          ->setNodeRef($node_ref_field)
          ->setSimpleAdsRef($simpleads_ref_field)
          ->load()) {
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

    if ($group) {
      $res->addCacheableDependency($group);
    }
    return $res;
  }

}
