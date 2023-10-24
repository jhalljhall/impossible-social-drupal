<?php

namespace Drupal\legal\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Class LegalController.
 *
 * @package Drupal\legal\Controller
 */
class LegalController extends ControllerBase {

  /**
   * Page callback.
   *
   * @return array
   *   Render array of terms and conditions.
   */
  public function legalPageAction() {

    $language   = $this->languageManager()->getCurrentLanguage();
    $conditions = legal_get_conditions($language->getId());

    return [
      '#type' => 'markup',
      '#markup' => check_markup($conditions['conditions'], $conditions['format'], $conditions['language']),
    ];
  }

}
