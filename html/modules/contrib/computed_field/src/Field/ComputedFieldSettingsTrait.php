<?php

namespace Drupal\computed_field\Field;

/**
 * Trait for our field definition classes.
 *
 * This allows the computed field value plugin to return values for field
 * definition settings, and defaults to the default values from the field type
 * plugin for settings that the plugin does not specify.
 */
trait ComputedFieldSettingsTrait {

  /**
   * Implements \Drupal\Core\TypedData\DataDefinitionInterface::getSettings().
   */
  public function getSettings() {
    // We need to at least return default values for the field's settings
    // appropriate to its type, even though in most cases we have nothing to
    // say. That's because other code may get settings and what we return must
    // be of the correct type to avoid errors.
    $field_type_manager = \Drupal::service('plugin.manager.field.field_type');
    $type = $this->getType();
    $default_settings = $field_type_manager->getDefaultStorageSettings($type) + $field_type_manager->getDefaultFieldSettings($type);

    // Allow the computed field plugin to provide settings.
    $plugin = $this->getFieldValuePlugin();
    $plugin_field_settings = $plugin->getFieldDefinitionSettings();

    return $plugin_field_settings + $default_settings;
  }

  /**
   * Implements \Drupal\Core\TypedData\DataDefinitionInterface::getSetting().
   */
  public function getSetting($setting_name) {
    $settings = $this->getSettings();
    return $settings[$setting_name] ?? NULL;
  }

}
