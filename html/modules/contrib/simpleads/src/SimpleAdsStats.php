<?php

namespace Drupal\simpleads;

use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\simpleads\Form\BaseSettingsForm;
use Drupal\Core\Datetime\Entity\DateFormat;

/**
 * SimpleAdsStats module helper.
 *
 * @ingroup simpleads
 */
class SimpleAdsStats {

  use StringTranslationTrait;

  /**
   * Advertisement entity ID.
   *
   * @var int
   */
  protected $entity_id;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a new SimpleAdsStats.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(Connection $connection, ConfigFactoryInterface $config_factory) {
    $this->connection = $connection;
    $this->configFactory = $config_factory;
  }

  /**
   * Set advertisement ID.
   */
  public function setEntityId($entity_id) {
    $this->entity_id = $entity_id;
    return $this;
  }

  /**
   * Get advertisement ID.
   */
  public function getEntityId() {
    return $this->entity_id;
  }

  /**
   * Load all advertisement statistics (only aggregates statistics).
   */
  public function loadAll() {
    $stats = [];
    if ($this->getEntityId()) {
      // Todays stats.
      if ($today_stats = $this->loadTodayData()) {
        $today_stats['ctr'] = $today_stats['ctr']  . '%';
        $stats[] = $today_stats;
      }
      $result = $this->connection->select('simpleads_stats', 's')
        ->fields('s', ['date', 'clicks', 'clicks_unique', 'impressions', 'impressions_unique'])
        ->condition('s.entity_id', $this->getEntityId())
        ->orderBy('s.date', 'DESC')
        ->execute();
      foreach ($result as $row) {
        $date = \DateTime::createFromFormat('Ymd', $row->date);
        $stats[] = [
          'date'               => $date->format($this->getDateTimeFormat()),
          'clicks'             => $row->clicks,
          'impressions'        => $row->impressions,
          'clicks_unique'      => $row->clicks_unique,
          'impressions_unique' => $row->impressions_unique,
          'ctr'                => number_format($row->clicks_unique / $row->impressions_unique, 4, '.', '') . '%'
        ];
      }
    }
    return $stats;
  }

  /**
   * Load todays statistics.
   */
  public function loadTodayData() {
    $stats = [];
    if ($this->getEntityId()) {
      // Todays stats.
      if ($impressions = $this->getTodaysImpressions()) {
        $clicks_unique = $this->getTodaysUniqueClicks();
        $impressions_unique = $this->getTodaysUniqueImpressions();
        $stats = [
          'date'               => $this->t('Today'),
          'clicks'             => $this->getTodaysClicks(),
          'impressions'        => $impressions,
          'clicks_unique'      => $clicks_unique,
          'impressions_unique' => $impressions_unique,
          'ctr'                => number_format($clicks_unique / $impressions_unique, 4, '.', '')
        ];
      }
    }
    return $stats;
  }

  /**
   * Load all advertisement statistics data.
   */
  public function loadData() {
    $stats = [];
    if ($entity_id = $this->getEntityId()) {
      // Todays stats.
      if ($today_stats = $this->loadTodayData()) {
        $date = \DateTime::createFromFormat('YMd', date('YMd'));
        $today_stats['month'] = $date->format('F');
        $today_stats['day'] = $date->format('d');
        $today_stats['year'] = $date->format('Y');
        $today_stats['weekday'] = $date->format('w');
        $stats[] = $today_stats;
      }
      $result = $this->connection->query('SELECT
          DATE_FORMAT(FROM_UNIXTIME(timestamp), \'%M\') AS "month",
          DATE_FORMAT(FROM_UNIXTIME(timestamp), \'%d\') AS "day",
          DATE_FORMAT(FROM_UNIXTIME(timestamp), \'%Y\') AS "year",
          SUM(clicks) AS "clicks",
          SUM(clicks_unique) AS "clicks_unique",
          SUM(impressions) AS "impressions",
          SUM(impressions_unique) AS "impressions_unique"
        FROM {simpleads_stats}
        WHERE entity_id = :entity_id
        GROUP BY DATE_FORMAT(FROM_UNIXTIME(timestamp), \'%M, %Y\'), timestamp
        ORDER BY timestamp', ['entity_id' => $entity_id]);
      foreach ($result as $row) {
        $date = \DateTime::createFromFormat('YMd', $row->year . $row->month . $row->day);
        $stats[] = [
          'date'               => $date->format($this->getDateTimeFormat()),
          'month'              => $this->t('@month', ['@month' => $date->format('F')]),
          'day'                => $date->format('d'),
          'year'               => $date->format('Y'),
          'weekday'            => $date->format('w'),
          'clicks'             => $row->clicks,
          'impressions'        => $row->impressions,
          'clicks_unique'      => $row->clicks_unique,
          'impressions_unique' => $row->impressions_unique,
          'ctr'                => number_format($row->clicks_unique / $row->impressions_unique, 4, '.', '')
        ];
      }
    }
    return $stats;
  }

  /**
   * Load advertisement clicks (including todays stats).
   */
  public function getClicks($todayOnly) {
    $todays_count = $this->getTodaysClicks();
    if ($todayOnly) {
      return $todays_count;
    }
    else {
      $query = $this->connection->select('simpleads_stats', 's');
      $query->addExpression('SUM(s.clicks)', 'total_count');
      $query->condition('s.entity_id', $this->getEntityId());
      $count = (int) $query->execute()->fetchField();
      return $count + $todays_count;
    }
  }

  /**
   * Load advertisement impressions (including todays stats).
   */
  public function getImpressions($todayOnly) {
    $todays_count = $this->getTodaysImpressions();
    if ($todayOnly) {
      return $todays_count;
    }
    else {
      $query = $this->connection->select('simpleads_stats', 's');
      $query->addExpression('SUM(s.impressions)', 'total_count');
      $query->condition('s.entity_id', $this->getEntityId());
      $count = (int) $query->execute()->fetchField();
      return $count + $todays_count;
    }
  }

  /**
   * Get all todays clicks.
   */
  public function getTodaysClicks() {
    return (int) $this->connection->select('simpleads_clicks')->condition('entity_id', $this->getEntityId())
      ->countQuery()->execute()->fetchField();
  }

  /**
   * Get all todays unique clicks.
   */
  public function getTodaysUniqueClicks() {
    $stat = $this->connection->query("SELECT COUNT(DISTINCT s.ip_address) AS unique_count
      FROM {simpleads_impressions} s
      WHERE s.entity_id = :id
      GROUP BY FROM_UNIXTIME(s.timestamp, '%Y%m%d'), s.entity_id", [':id' => $this->getEntityId()])->fetchObject();
    return (int) $stat->unique_count;
  }

  /**
   * Get all todays impressions.
   */
  public function getTodaysImpressions() {
    return (int) $this->connection->select('simpleads_impressions')->condition('entity_id', $this->getEntityId())
      ->countQuery()->execute()->fetchField();
  }

  /**
   * Get all todays unique impressions.
   */
  public function getTodaysUniqueImpressions() {
    $stat = $this->connection->query("SELECT COUNT(DISTINCT s.ip_address) AS unique_count
      FROM {simpleads_impressions} s
      WHERE s.entity_id = :id
      GROUP BY FROM_UNIXTIME(s.timestamp, '%Y%m%d'), s.entity_id", [':id' => $this->getEntityId()])->fetchObject();
    return (int) $stat->unique_count;
  }

  /**
   * Generate test data.
   */
  public function generateTestData() {
    for ($i = 1; $i <= 12; $i++) {
      for ($j = 1; $j <= 28; $j++) {
        $ik = ($i < 10) ? '0' . $i : $i;
        $jk = ($j < 10) ? '0' . $j : $j;
        $year = date('Y');
        $clicks = rand(1, 100);
        $impressions = rand(10, 500);
        $this->connection->insert('simpleads_stats')
          ->fields([
            'entity_id' => 1,
            'date' => $year . $ik . $jk,
            'timestamp' => strtotime($year . $ik . $jk),
            'clicks' => $clicks + rand(1, 100),
            'clicks_unique' => $clicks,
            'impressions' => $impressions + rand(1, 500),
            'impressions_unique' => $impressions,
          ])
          ->execute();
      }
    }
  }

  /**
   * Datetime format.
   */
  private function getDateTimeFormat() {
    $date_format_id = $this->configFactory->get(BaseSettingsForm::CONFIG_NAME)->get('stats_date_format');
    $format = 'F d, Y';
    if ($date_format_entity = DateFormat::load($date_format_id)) {
      $format = $date_format_entity->getPattern();
    }
    return $format;
  }

}
