<?php

namespace Drupal\commerce_webform_order;

use Drupal\Core\Session\AccountInterface;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Interface for the webform submission access checker.
 */
interface AccessCheckerInterface {

  /**
   * Checks update access.
   *
   * Use \Drupal\Core\Entity\EntityAccessControlHandlerInterface::createAccess()
   * to check access to create an entity.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform submission for which to check access.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user session for which to check access.
   *
   * @return bool|\Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function updateAccess(WebformSubmissionInterface $webform_submission, AccountInterface $account);

}
