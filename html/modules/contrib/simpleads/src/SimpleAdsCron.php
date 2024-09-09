<?php

namespace Drupal\simpleads;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Component\Datetime\TimeInterface;

/**
 * SimpleAds Cron routine.
 *
 * @ingroup simpleads
 */
class SimpleAdsCron {

  /**
   * Aggregated advertisement entities.
   *
   * @var array
   */
  protected $simpleads = [];

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a SimpleAds module object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(Connection $connection, EntityTypeManagerInterface $entityTypeManager, StateInterface $state, TimeInterface $time) {
    $this->connection = $connection;
    $this->entityTypeManager = $entityTypeManager;
    $this->state = $state;
    $this->time = $time;
  }

  /**
   * Initiate cron task routines.
   */
  public function init() {
    $current_timestamp = $this->time->getRequestTime();
    // Make sure we only aggregate data once a day.
    $last_run = $this->state->get('simpleads_last_aggregation_time', 0);
    if (date('m-d-Y', $last_run) != date('m-d-Y', $current_timestamp)) {
      $this->runAggregation();
      $this->state->set('simpleads_last_aggregation_time', $current_timestamp);
    }
    $this->checkSimpleAds();
  }

  /**
   * Aggregate statistics.
   */
  protected function runAggregation() {
    // Aggregate daily impressions.
    $result = $this->connection->query("SELECT FROM_UNIXTIME(s.timestamp, '%Y%m%d') AS date, s.entity_id, COUNT(*) AS all_count, COUNT(DISTINCT s.ip_address) AS unique_count
      FROM {simpleads_impressions} s
      WHERE FROM_UNIXTIME(s.timestamp, '%Y%m%d') != FROM_UNIXTIME(UNIX_TIMESTAMP(), '%Y%m%d')
      GROUP BY date, s.entity_id");
    foreach ($result as $row) {
      $this->connection->merge('simpleads_stats')
        ->key([
          'entity_id' => $row->entity_id,
          'date'      => $row->date
        ])
        ->fields([
          'entity_id'          => $row->entity_id,
          'date'               => $row->date,
          'timestamp'          => $this->convertFromUTC($row->date)->format('U'),
          'clicks'             => 0,
          'clicks_unique'      => 0,
          'impressions'        => $row->all_count,
          'impressions_unique' => $row->unique_count,
        ])
        ->execute();
      if (!in_array($row->entity_id, $this->simpleads)) {
        $this->simpleads[] = $row->entity_id;
      }
    }

    // Aggregate daily clicks.
    $result = $this->connection->query("SELECT FROM_UNIXTIME(s.timestamp, '%Y%m%d') AS date, s.entity_id, COUNT(*) AS all_count, COUNT(DISTINCT s.ip_address) AS unique_count
      FROM {simpleads_clicks} s
      WHERE FROM_UNIXTIME(s.timestamp, '%Y%m%d') != FROM_UNIXTIME(UNIX_TIMESTAMP(), '%Y%m%d')
      GROUP BY date, s.entity_id");
    foreach ($result as $row) {
      $this->connection->update('simpleads_stats')
        ->fields([
          'clicks'        => $row->all_count,
          'clicks_unique' => $row->unique_count,
        ])
        ->condition('date', $row->date)
        ->condition('entity_id', $row->entity_id)
        ->execute();
      if (!in_array($row->entity_id, $this->simpleads)) {
        $this->simpleads[] = $row->entity_id;
      }
    }

    // Remove aggregated stats from daily stats table.
    if (!empty($this->simpleads)) {
      $this->connection->delete('simpleads_impressions')
        ->condition('entity_id', $this->simpleads, 'IN')
        ->execute();
      $this->connection->delete('simpleads_clicks')
        ->condition('entity_id', $this->simpleads, 'IN')
        ->execute();
    }
  }

  /**
   * Update SimpleAds entities and set published status to active/inactive based on dates.
   */
  protected function checkSimpleAds() {
    $entities = $this->entityTypeManager->getStorage('simpleads')->loadByProperties([
      'inactive' => FALSE,
    ]);
    foreach ($entities as $entity) {
      if (!$campaign = $entity->getCampaign()) {
        $entity->save();
      }
    }
  }

  /**
   * Convert UTC datetime to date with current timezone.
   *
   * @param $date_string
   * @return \DateTime
   */
  protected function convertFromUTC($date_string) {
    $date = new \DateTime($date_string, new \DateTimeZone('UTC') );
    $date->setTimeZone(new \DateTimeZone(date_default_timezone_get()));
    return $date;
  }

}
