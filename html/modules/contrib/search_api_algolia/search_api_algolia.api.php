<?php

/**
 * @file
 * Hooks provided by the Search API Algolia search module.
 */

use Drupal\search_api\IndexInterface;

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter Algolia objects before they are sent to Algolia for indexing.
 *
 * @param array $objects
 *   An array of objects ready to be indexed, generated from $items array.
 * @param \Drupal\search_api\IndexInterface $index
 *   The search index for which items are being indexed.
 * @param \Drupal\search_api\Item\ItemInterface[] $items
 *   An array of items to be indexed, keyed by their item IDs.
 */
function hook_search_api_algolia_objects_alter(array &$objects, IndexInterface $index, array $items) {
  // Adds a "foo" field with value "bar" to all documents.
  foreach ($objects as $key => $object) {
    $objects[$key]['foo'] = 'bar';
  }
}

/**
 * Allow other modules to remove sorts handled in index rankings.
 *
 * @param array $sorts
 *   Sorts from query.
 * @param \Drupal\search_api\IndexInterface $index
 *   Index.
 */
function hook_search_api_algolia_sorts_alter(array &$sorts, IndexInterface $index) {
  unset($sorts['stock']);
}

/**
 * @} End of "addtogroup hooks".
 */
