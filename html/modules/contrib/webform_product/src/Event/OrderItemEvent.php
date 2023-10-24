<?php

namespace Drupal\webform_product\Event;

use Drupal\webform\WebformSubmissionInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * Class OrderItemEvent.
 *
 * Provides an event to let other modules alter the order_item list.
 *
 * @package Drupal\webform_product\Event
 */
class OrderItemEvent extends Event {

  const EVENT_NAME = 'webform_product_order_item';

  /**
   * The webform submission.
   *
   * @var \Drupal\webform\WebformSubmissionInterface
   */
  public $webformSubmission;

  /**
   * Array containing the order items initially resolved by the module.
   *
   * @var array
   */
  public $orderItems;

  /**
   * The webform_product configuration.
   *
   * @var array
   */
  public $configuration;

  /**
   * OrderItemEvent constructor.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webformSubmission
   *   The webform submission.
   * @param array $orderItems
   *   The order items array.
   * @param array $configuration
   *   The webform_product configuration.
   */
  public function __construct(WebformSubmissionInterface $webformSubmission, array &$orderItems, array $configuration) {
    $this->webformSubmission = $webformSubmission;
    $this->orderItems = &$orderItems;
    $this->configuration = $configuration;
  }

}
