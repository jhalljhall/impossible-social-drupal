<?php

namespace Drupal\simpleads\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\simpleads\Entity\AdvertisementInterface;

/**
 * Provides an interface for defining Campaign entities.
 *
 * @ingroup simpleads
 */
interface CampaignInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  // Add get/set methods for your configuration properties here.

  /**
   * Gets the Campaign name.
   *
   * @return string
   *   Name of the Campaign.
   */
  public function getName();

  /**
   * Sets the Campaign name.
   *
   * @param string $name
   *   The Campaign name.
   *
   * @return \Drupal\simpleads\Entity\CampaignInterface
   *   The called Campaign entity.
   */
  public function setName($name);

  /**
   * Gets the Campaign start date.
   *
   * @return date
   *   Start date of the Campaign.
   */
  public function getStartDate();

  /**
   * Sets the Campaign start date.
   *
   * @param date $date
   *   The Campaign start date.
   *
   * @return \Drupal\simpleads\Entity\SimpleAdsEntityInterface
   *   The called Campaign entity.
   */
  public function setStartDate($date);

  /**
   * Gets the Campaign current date.
   *
   * @return date
   *   Current date of the Campaign.
   */
  public function getCurrentDate();

  /**
   * Gets the Campaign end date.
   *
   * @return date
   *   Start date of the Campaign.
   */
  public function getEndDate();

  /**
   * Sets the Campaign end date.
   *
   * @param date $date
   *   The Campaign end date.
   *
   * @return \Drupal\simpleads\Entity\SimpleAdsEntityInterface
   *   The called Campaign entity.
   */
  public function setEndDate($date);

  /**
   * Gets the Campaign type.
   *
   * @return string
   *   Return Campaign type.
   */
  public function getType();

  /**
   * Gets the Campaign creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Campaign.
   */
  public function getCreatedTime();

  /**
   * Sets the Campaign creation timestamp.
   *
   * @param int $timestamp
   *   The Campaign creation timestamp.
   *
   * @return \Drupal\simpleads\Entity\CampaignInterface
   *   The called Campaign entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Campaign status indicator.
   *
   * Unpublished Campaign are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Campaign is active.
   */
  public function isActive();

  /**
   * Sets the status of a Campaign.
   *
   * @param bool $status
   *   TRUE to set this Campaign to make it active, FALSE to set it to disable.
   *
   * @return \Drupal\simpleads\Entity\GroupInterface
   *   The called Campaign entity.
   */
  public function setActive($status);

  /**
   * Gets the Campaign click limit.
   *
   * @return int
   *   Click value limit.
   */
  public function getClickLimit();

  /**
   * Gets the Campaign impression limit.
   *
   * @return int
   *   Impression value limit.
   */
  public function getImpressionLimit();

  /**
   * Check if campaign is still active.
   *
   * @return bool
   *   TRUE if the Campaign start/end date is in range.
   */
  public function isWithinDateRange();

  /**
   * Check if advertisement parameters against campaign parameters.
   *
   * @param \Drupal\simpleads\Entity\AdvertisementInterface $advertisement
   *   Advertisement entity.
   *
   * @return bool
   *   TRUE if the ad is still running.
   */
  public function isRunning(AdvertisementInterface $advertisement);

}
