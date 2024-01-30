<?php

namespace Drupal\commerce_webform_order\Element;

use Drupal\Component\Utility\Tags;
use Drupal\Core\Entity\Element\EntityAutocomplete as EntityAutocompleteBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionWithAutocreateInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a custom entity autocomplete form element.
 *
 * @FormElement("commerce_webform_order_entity_autocomplete_token")
 */
class EntityAutocompleteOrToken extends EntityAutocompleteBase {

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    // Process the #default_value property.
    if ($input === FALSE && isset($element['#default_value']) && $element['#process_default_value']) {
      if (is_array($element['#default_value']) && $element['#tags'] !== TRUE) {
        throw new \InvalidArgumentException('The #default_value property is an array but the form element does not allow multiple values.');
      }
      elseif (!is_array($element['#default_value'])) {
        // Convert the default value into an array for easier processing in
        // static::getEntityLabels().
        $element['#default_value'] = [$element['#default_value']];
      }

      if (!(reset($element['#default_value']) instanceof EntityInterface)) {
        // If there is a token value, just return the value.
        if (self::hasTokens($element['#default_value'])) {
          return implode(', ', $element['#default_value']);
        }

        // Otherwise try to load the values.
        $element['#default_value'] = \Drupal::entityTypeManager()->getStorage($element['#target_type'])->loadMultiple($element['#default_value']);

        if (!(reset($element['#default_value']) instanceof EntityInterface)) {
          throw new \InvalidArgumentException('The #default_value property has to be an entity object or an array of entity objects.');
        }
      }

      // Extract the labels from the passed-in entity objects, taking access
      // checks into account.
      return static::getEntityLabels($element['#default_value']);
    }

    // Potentially the #value is set directly, so it contains the 'target_id'
    // array structure instead of a string.
    if ($input !== FALSE && is_array($input)) {
      $entity_ids = array_map(function (array $item) {
        return $item['target_id'];
      }, $input);

      if (self::hasTokens($entity_ids)) {
        return implode(', ', $entity_ids);
      }

      $entities = \Drupal::entityTypeManager()->getStorage($element['#target_type'])->loadMultiple($entity_ids);

      return static::getEntityLabels($entities);
    }
  }

  /**
   * Form element validation handler for entity_autocomplete elements.
   */
  public static function validateEntityAutocomplete(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $value = NULL;

    if (!empty($element['#value'])) {
      $options = $element['#selection_settings'] + [
        'target_type' => $element['#target_type'],
        'handler' => $element['#selection_handler'],
      ];
      /** @var /Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface $handler */
      $handler = \Drupal::service('plugin.manager.entity_reference_selection')->getInstance($options);
      $autocreate = (bool) $element['#autocreate'] && $handler instanceof SelectionWithAutocreateInterface;

      // GET forms might pass the validated data around on the next request, in
      // which case it will already be in the expected format.
      if (is_array($element['#value'])) {
        $value = $element['#value'];
      }
      else {
        $input_values = $element['#tags'] ? Tags::explode($element['#value']) : [$element['#value']];

        foreach ($input_values as $input) {
          $match = static::extractEntityIdFromAutocompleteInput($input);
          if ($match === NULL) {
            $tokens = \Drupal::token()->scan($input);
            // If there is a token value, just return the value.
            if (count($tokens)) {
              // Do not search for input string.
              $match = $input;
            }
            else {
              // Try to get a match from the input string when the user didn't
              // use the autocomplete but filled in a value manually.
              $match = static::matchEntityByTitle($handler, $input, $element, $form_state, !$autocreate);
            }
          }

          if ($match !== NULL) {
            $value[] = [
              'target_id' => $match,
            ];
          }
          elseif ($autocreate) {
            /** @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionWithAutocreateInterface $handler */
            // Auto-create item. See an example of how this is handled in
            // \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem::presave().
            $value[] = [
              'entity' => $handler->createNewEntity($element['#target_type'], $element['#autocreate']['bundle'], $input, $element['#autocreate']['uid']),
            ];
          }
        }
      }

      // Check that the referenced entities are valid, if needed.
      if ($element['#validate_reference'] && !empty($value)) {
        // Validate existing entities.
        $ids = array_reduce($value, function ($return, $item) {
          if (isset($item['target_id'])) {
            $tokens = \Drupal::token()->scan($item['target_id']);
            if (empty($tokens)) {
              $return[] = $item['target_id'];
            }
          }

          return $return;
        });

        if ($ids) {
          $valid_ids = $handler->validateReferenceableEntities($ids);
          if ($invalid_ids = array_diff($ids, $valid_ids)) {
            foreach ($invalid_ids as $invalid_id) {
              $t_args = [
                '%type' => $element['#target_type'],
                '%id' => $invalid_id,
              ];
              $form_state->setError($element, t('The referenced entity (%type: %id) does not exist.', $t_args));
            }
          }
        }

        // Validate newly created entities.
        $new_entities = array_reduce($value, function ($return, $item) {
          if (isset($item['entity'])) {
            $return[] = $item['entity'];
          }
          return $return;
        });

        if ($new_entities) {
          if ($autocreate) {
            $valid_new_entities = $handler->validateReferenceableNewEntities($new_entities);
            $invalid_new_entities = array_diff_key($new_entities, $valid_new_entities);
          }
          else {
            // If the selection handler does not support referencing newly
            // created entities, all of them should be invalidated.
            $invalid_new_entities = $new_entities;
          }

          foreach ($invalid_new_entities as $entity) {
            /** @var \Drupal\Core\Entity\EntityInterface $entity */
            $t_args = [
              '%type' => $element['#target_type'],
              '%label' => $entity->label(),
            ];
            $form_state->setError($element, t('This entity (%type: %label) cannot be referenced.', $t_args));
          }
        }
      }

      // Use only the last value if the form element does not support multiple
      // matches (tags).
      if (!$element['#tags'] && !empty($value)) {
        $last_value = $value[count($value) - 1];
        $value = $last_value['target_id'] ?? $last_value;
      }
    }

    $form_state->setValueForElement($element, $value);
  }

  /**
   * Checks if there is a token value.
   *
   * @param array $values
   *   The element values.
   *
   * @return bool
   *   TRUE if there is a token value.
   */
  public static function hasTokens(array $values) {
    foreach ($values as $value) {
      if (count(\Drupal::token()->scan($value))) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
