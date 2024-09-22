<?php

namespace Drupal\computed_field\Plugin\ComputedField;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\PluginBase;
use Drupal\computed_field\Field\ComputedFieldDefinition;
use Drupal\computed_field\Field\ComputedFieldDefinitionWithValuePluginInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Base class for Computed Field plugins.
 */
abstract class ComputedFieldBase extends PluginBase implements ComputedFieldPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function getFieldType(): string {
    return $this->pluginDefinition['field_type'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldName(): ?string {
    return $this->pluginDefinition['attach']['field_name'] ?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldLabel(): string {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinitionSettings(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function useLazyBuilder(EntityInterface $host_entity, ComputedFieldDefinitionWithValuePluginInterface $computed_field_definition): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheability(EntityInterface $host_entity, ComputedFieldDefinitionWithValuePluginInterface $computed_field_definition): ?CacheableMetadata {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldCardinality(): int {
    return $this->pluginDefinition['cardinality'];
  }

  /**
   * {@inheritdoc}
   */
  public function attachAsBaseField($fields, EntityTypeInterface $entity_type): bool {
    if (!isset($this->pluginDefinition['attach']['scope'])) {
      throw new InvalidPluginDefinitionException($this->pluginId, "The 'scope' key must be specified in the 'attach' array.");
    }

    return ($this->pluginDefinition['attach']['scope'] == 'base');
  }

  /**
   * {@inheritdoc}
   */
  public function attachAsBundleField($fields, EntityTypeInterface $entity_type, string $bundle): bool {
    if (!isset($this->pluginDefinition['attach']['scope'])) {
      throw new InvalidPluginDefinitionException($this->pluginId, "The 'scope' key must be specified in the 'attach' array.");
    }

    return (
      $this->pluginDefinition['attach']['scope'] == 'bundle'
      &&
      in_array($bundle, $this->pluginDefinition['attach']['entity_types'][$entity_type->id()])
    );
  }

}
