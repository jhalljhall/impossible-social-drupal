<?php

/**
 * @file
 * Views integration for Commerce Webform Order module.
 */

/**
 * Implements hook_views_data_alter().
 */
function commerce_webform_order_views_data_alter(array &$data) {
  // Add relationship from webform submission to order items it has created.
  $entity_type_manager = \Drupal::entityTypeManager();
  $submission_definition = $entity_type_manager->getDefinition('webform_submission');
  $order_item_definition = $entity_type_manager->getDefinition('commerce_order_item');
  $order_item_storage = $entity_type_manager->getStorage('commerce_order_item');
  $base_table = $order_item_storage->getBaseTable();

  $data[$submission_definition->getDataTable()][$base_table]['relationship'] = [
    'title' => t('Order items'),
    'label' => t('Order items'),
    'help' => t('Order items the webform submission has created.'),
    'base' => $base_table,
    'base field' => $order_item_definition->getKey('id'),
    'relationship field' => $submission_definition->getKey('id'),
    'id' => 'standard',
  ];
}
