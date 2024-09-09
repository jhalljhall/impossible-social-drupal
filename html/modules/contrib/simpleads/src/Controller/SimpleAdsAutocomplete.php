<?php

namespace Drupal\simpleads\Controller;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Xss;
use Drupal\simpleads\Entity\Advertisement;

/**
 * Controller to display SimpleAds autocomplete.
 */
class SimpleAdsAutocomplete {

  public function handleAutocomplete(Request $request) {
    $results = [];
    $input = $request->query->get('q');
    if (!$input) {
      return new JsonResponse($results);
    }
    $input = Xss::filter($input);
    $query = \Drupal::entityQuery('simpleads')
      ->condition('title', $input, 'CONTAINS')
      ->condition('status', 1)
      ->groupBy('nid')
      ->sort('created', 'DESC')
      ->range(0, 25);
    $query->accessCheck();
    $ids = $query->execute();
    $nodes = $ids ? Advertisement::loadMultiple($ids) : [];
    foreach ($nodes as $node) {
      $results[] = [
        'value' => EntityAutocomplete::getEntityLabels([$node]),
        'label' => $node->getTitle() . ' (' . $node->id() . ')',
      ];
    }
    return new JsonResponse($results);
  }

}
