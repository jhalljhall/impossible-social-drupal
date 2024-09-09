<?php

namespace Drupal\simpleads\Entity\AccessControlHandler;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Group entity.
 *
 * @see \Drupal\simpleads\Entity\Group.
 */
class GroupAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\simpleads\Entity\GroupInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isActive()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished simpleads_group entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published simpleads_group entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit simpleads_group entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete simpleads_group entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add simpleads_group entities');
  }

}
