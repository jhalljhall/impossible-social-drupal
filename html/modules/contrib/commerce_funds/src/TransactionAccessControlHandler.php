<?php

namespace Drupal\commerce_funds;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a Transaction access control handler.
 */
class TransactionAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\commerce_funds\TransactionInterface $entity */
    if ($operation == 'view') {
      if ($account->hasPermission('administer transactions')) {
        return AccessResult::allowed();
      }
      elseif ($account->id() == $entity->getIssuerId() || $account->id() == $entity->getRecipientId()) {
        return AccessResult::allowed();
      }
    }
    if ($operation == 'delete') {
      return AccessResult::forbidden('Transaction should not be deleted but only canceled.');
    }

    // No opinion.
    return AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    if ($entity_bundle == 'deposit' && $account->hasPermission('deposit funds')) {
      return AccessResult::allowed();
    }
    elseif ($entity_bundle == 'transfer' && $account->hasPermission('transfer funds') || $entity_bundle == 'payment' && $account->hasPermission('deposit funds')) {
      return AccessResult::allowed();
    }
    elseif ($entity_bundle == 'escrow' && $account->hasPermission('create escrow payment')) {
      return AccessResult::allowed();
    }
    elseif ($entity_bundle == 'withdrawal_request' && $account->hasPermission('administer withdrawal requests')) {
      return AccessResult::allowed();
    }
    elseif ($entity_bundle == 'conversion' && $account->hasPermission('convert currencies')) {
      return AccessResult::allowed();
    }

    // No opinion.
    return AccessResult::forbidden();
  }

}
