<?php

namespace Drupal\simpleads\Entity\AccessControlHandler;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Campaign entity.
 *
 * @see \Drupal\simpleads\Entity\Campaign.
 */
class CampaignAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\simpleads\Entity\CampaignInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isActive()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished simpleads_campaign entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published simpleads_campaign entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit simpleads_campaign entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete simpleads_campaign entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add simpleads_campaign entities');
  }

}
