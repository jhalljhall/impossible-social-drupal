<?php

namespace Drupal\simpleads\Entity\AccessControlHandler;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Advertisement entity.
 *
 * @see \Drupal\simpleads\Entity\Advertisement.
 */
class AdvertisementAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\simpleads\Entity\AdvertisementInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isActive()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished simpleads entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published simpleads entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit simpleads entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete simpleads entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add simpleads entities');
  }

}
