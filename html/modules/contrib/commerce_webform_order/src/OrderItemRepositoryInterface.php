<?php

namespace Drupal\commerce_webform_order;

use Drupal\webform\WebformSubmissionInterface;

/**
 * Interface for the commerce order item repository.
 */
interface OrderItemRepositoryInterface {

  /**
   * Returns the order item created by the specific webform submission.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform submission.
   * @param mixed $handler_id
   *   The webform handler ID.
   *
   * @return \Drupal\commerce_order\Entity\OrderItemInterface[]|null
   *   The related order items.
   */
  public function getAllByWebformSubmission(WebformSubmissionInterface $webform_submission, $handler_id = FALSE);

  /**
   * Returns the last order item created by the specific webform submission.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform submission.
   * @param mixed $handler_id
   *   The webform handler ID.
   *
   * @return \Drupal\commerce_order\Entity\OrderItemInterface|null
   *   The related order item.
   */
  public function getLastByWebformSubmission(WebformSubmissionInterface $webform_submission, $handler_id = FALSE);

}
