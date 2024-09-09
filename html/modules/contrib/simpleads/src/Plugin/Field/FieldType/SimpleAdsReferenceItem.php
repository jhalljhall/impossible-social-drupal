<?php

namespace Drupal\simpleads\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Plugin\Validation\Constraint\AllowedValuesConstraint;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'simpleads_reference' field type.
 *
 * @FieldType(
 *   id = "simpleads_reference",
 *   label = @Translation("SimpleAds Reference"),
 *   description = @Translation("SimpleAds Reference"),
 *   default_widget = "simpleads_reference",
 *   default_formatter = "simpleads_reference"
 * )
 */
class SimpleAdsReferenceItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['target_id'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Advertisement'))
      ->setDescription(new TranslatableMarkup('SimpleAds reference.'));
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return 'target_id';
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema['columns'] = [
      'target_id' => [
        'type' => 'int',
        'not null' => FALSE,
      ],
    ];
    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $target_id = $this->get('target_id')->getValue();
    return $target_id === NULL || $target_id === '';
  }

}
