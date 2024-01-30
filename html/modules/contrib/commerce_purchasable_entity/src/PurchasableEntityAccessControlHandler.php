<?php

namespace Drupal\commerce_purchasable_entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the purchasable entity.
 */
class PurchasableEntityAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermissions(
          $account,
          [
            'view commerce_purchasable_entity',
            'administer commerce_purchasable_entity_type',
          ],
          'OR',
        );

      case 'update':
        return AccessResult::allowedIfHasPermissions(
          $account,
          [
            'edit commerce_purchasable_entity',
            'administer commerce_purchasable_entity_type',
          ],
          'OR',
        );

      case 'delete':
        return AccessResult::allowedIfHasPermissions(
          $account,
          [
            'delete commerce_purchasable_entity',
            'administer commerce_purchasable_entity_type',
          ],
          'OR',
        );

      default:
        return AccessResult::neutral();
    }

  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions(
      $account,
      [
        'create commerce_purchasable_entity',
        'administer commerce_purchasable_entity_type',
      ],
      'OR',
    );
  }

}
