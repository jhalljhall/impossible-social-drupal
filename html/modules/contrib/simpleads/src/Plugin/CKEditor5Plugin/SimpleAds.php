<?php

declare(strict_types = 1);

namespace Drupal\simpleads\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\Core\Url;
use Drupal\editor\EditorInterface;

/**
 * Plugin class to add dialog url for SimpleAds.
 */
class SimpleAds extends CKEditor5PluginDefault {

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $static_plugin_config['SimpleAds']['dialogURL'] = Url::fromRoute('simpleads.ckeditor5_dialog')
      ->toString(TRUE)->getGeneratedUrl();
    $static_plugin_config['SimpleAds']['previewURL'] = Url::fromRoute('simpleads.ckeditor5_preview',
      ['editor' => $editor->id()])
      ->toString(TRUE)->getGeneratedUrl();
    return $static_plugin_config;
  }

}
