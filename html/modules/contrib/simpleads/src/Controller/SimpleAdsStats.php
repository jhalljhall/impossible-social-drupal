<?php

namespace Drupal\simpleads\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\simpleads\Form\BaseSettingsForm;

/**
 * Controller to display SimpleAds statistics.
 */
class SimpleAdsStats extends ControllerBase {

  public function index($simpleads = NULL) {
    $view_mode = $this->config(BaseSettingsForm::CONFIG_NAME)->get('stats_view_mode');
    $view_builder = $this->entityTypeManager()->getViewBuilder('simpleads');
    return $view_builder->view($simpleads, $view_mode);
  }

}
