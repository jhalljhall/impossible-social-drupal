<?php

namespace Drupal\im_commons\Controller;

use Drupal\im_commons\Utility\DescriptionTemplateTrait;

/**
 * Simple page controller for drupal.
 */
class IMCommonsController {

  use DescriptionTemplateTrait;

  /**
   * {@inheritdoc}
   */
  public function getModuleName() {
    return 'im_commons';
  }

}