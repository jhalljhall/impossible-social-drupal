<?php

namespace Drupal\Tests\commerce_funds\Unit\Services;

use Drupal\commerce_funds\FeesManager;

/**
 * Stump class for fees manager.
 */
class TestFeesManager extends FeesManager {

  /**
   * Override parent::getExchangeRates()
   */
  public function getExchangeRates() {
    return [
      'USD' => [
        'EUR' => [
          'value' => 1.2,
        ],
      ],
      'EUR' => [
        'USD' => [
          'value' => 0.8,
        ],
      ],
    ];
  }

}
