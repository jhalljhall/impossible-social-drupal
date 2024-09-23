<?php

namespace Drupal\computed_field\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Defines the Computed Field plugin annotation object.
 *
 * Plugin namespace: ComputedField.
 *
 * @Annotation
 */
class ComputedField extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id = '';

  /**
   * The human-readable name of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $label = '';

  /**
   * The field type.
   *
   * There is no data stored, but using existing types allows the computed field
   * to use existing field formatter plugins.
   *
   * @var string
   */
  public $field_type;

  /**
   * A boolean stating that fields of this type cannot be created in the UI.
   *
   * @var bool
   */
  public $no_ui = FALSE;

  /**
   * The cardinality of the field.
   *
   * @var int
   */
  public $cardinality = 1;

  /**
   * Definition of automatic attachment.
   *
   * If specified, this array must contain:
   *  - scope: The field scope. One of 'base' or 'bundle'.
   *  - field_name: The field name.
   *  - entity_types: An array of the entity types and bundles to attach to.
   *    Array keys are entity type IDs. If the scope is 'bundle', array values
   *    are arrays of bundle names. If the scope is 'base', the array values
   *    should be just empty arrays. Entity types and bundles that do not exist
   *    are ignored: it is safe to specify an entity type or bundles that might
   *    not be installed.
   *
   * @var array
   */
  public $attach = NULL;

}
