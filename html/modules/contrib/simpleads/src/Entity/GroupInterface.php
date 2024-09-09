<?php

namespace Drupal\simpleads\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Group entities.
 *
 * @ingroup simpleads
 */
interface GroupInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Gets the Group name.
   *
   * @return string
   *   Name of the Group.
   */
  public function getName();

  /**
   * Sets the Group name.
   *
   * @param string $name
   *   The Group name.
   *
   * @return \Drupal\simpleads\Entity\GroupInterface
   *   The called Group entity.
   */
  public function setName($name);

  /**
   * Gets the Group description.
   *
   * @return string
   *   Description of the Group.
   */
  public function getDescription();

  /**
   * Sets the Group description.
   *
   * @param string $description
   *   The Group description.
   *
   * @return \Drupal\simpleads\Entity\GroupInterface
   *   The called Group entity.
   */
  public function setDescription($description);

  /**
   * Gets the Group creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Group.
   */
  public function getCreatedTime();

  /**
   * Sets the Group creation timestamp.
   *
   * @param int $timestamp
   *   The Group creation timestamp.
   *
   * @return \Drupal\simpleads\Entity\GroupInterface
   *   The called Group entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Group status indicator.
   *
   * Unpublished Group are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Group is active.
   */
  public function isActive();

  /**
   * Sets the status of a Group.
   *
   * @param bool $status
   *   TRUE to set this Group to make it active, FALSE to set it to disable.
   *
   * @return \Drupal\simpleads\Entity\GroupInterface
   *   The called Group entity.
   */
  public function setActive($status);

}
