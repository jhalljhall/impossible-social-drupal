<?php

namespace Drupal\simpleads\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemInterface;

/**
 * Plugin implementation of the 'simpleads_stats' formatter.
 *
 * @FieldFormatter(
 *   id = "simpleads_stats",
 *   label = @Translation("Default"),
 *   field_types = {
 *     "simpleads_stats"
 *   }
 * )
 */
class SimpleAdsStatsFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#theme'  => 'table',
        '#empty'  => t('Statistics not available yet. Please make sure your Cron is running at least once a day and make sure this ad is Active.'),
        '#rows'   => $this->getStatistics($item->value),
        '#header' => [
          t('Date'), t('Clicks'), t('Impressions'), t('Unique Clicks'), t('Unique Impressions'), t('CTR'),
        ],
        '#cache' => [
          'tags'    => ['simpleads_group'],
          'context' => ['url.query_args'],
        ],
      ];
    }
    return $elements;
  }

  /**
   * Generate data for the stats table.
   */
  protected function getStatistics($entity) {
    $rows = [];
    if ($statistics = $entity->getStatistics()) {
      foreach ($statistics as $date => $columns) {
        $rows[]['data'] = $columns;
      }
    }
    return $rows;
  }

}
