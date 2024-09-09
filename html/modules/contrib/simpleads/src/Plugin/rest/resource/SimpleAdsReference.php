<?php

namespace Drupal\simpleads\Plugin\rest\resource;

use Drupal\simpleads\Entity\Group;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\simpleads\Model\Response;
use Drupal\simpleads\Model\SimpleAdsReference as SimpleAdsReferenceModel;

/**
 * Provides data for SimpleAds blocks.
 *
 * @RestResource(
 *   id = "simpleads_reference",
 *   label = @Translation("SimpleAds Reference"),
 *   uri_paths = {
 *     "canonical" = "/simpleads/reference/{entity_type}/{field_name}/{entity_id}",
 *     "create" = "/simpleads/reference/{entity_type}/{field_name}/{entity_id}"
 *   }
 * )
 */
class SimpleAdsReference extends ResourceBase {

  public function get($entity_type, $field_name, $entity_id = NULL) {
    $response = new Response();
    $ids = [];
    $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id);
    $field_type = $entity->get($field_name)->getFieldDefinition()->getType();
    if ($field_type != 'simpleads_reference') {
      $response->setCode('invalid_reference_field');
    }
    else {
      if (empty($entity_id)) {
        $response->setCode('invalid_reference');
      }
      else if (!$entity) {
        $response->setCode('reference_not_found');
      }
      else {
        if ($values = $entity->get($field_name)->getValue()) {
          foreach ($values as $item) {
            $ids[] = $item['target_id'];
          }
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
    }

    $res = new ResourceResponse($response->model());
    $res->getCacheableMetadata()->addCacheContexts(['user.permissions', 'url.query_args']);
    $res->getCacheableMetadata()->addCacheTags(['simpleads_group']);

    if ($entity) {
      $res->addCacheableDependency($entity);
    }
    return $res;
  }

}
