<?php

namespace Drupal\commerce_funds;

use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Defines the TransactionStorageSchema schema handler.
 */
class TransactionStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getSharedTableFieldSchema(FieldStorageDefinitionInterface $storage_definition, $table_name, array $column_mapping) {
    $schema = parent::getSharedTableFieldSchema($storage_definition, $table_name, $column_mapping);
    $field_name = $storage_definition->getName();

    if ($table_name == 'commerce_funds_transactions') {
      switch ($field_name) {
        case 'hash':
          $this->addSharedTableFieldIndex($storage_definition, $schema, TRUE);
          break;
      }
    }

    return $schema;
  }

}
