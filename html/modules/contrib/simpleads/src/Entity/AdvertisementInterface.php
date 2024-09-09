<?php

namespace Drupal\simpleads\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Advertisement entities.
 *
 * @ingroup simpleads
 */
interface AdvertisementInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Gets the SimpleAds title.
   *
   * @return string
   *   Title of the SimpleAds.
   */
  public function getTitle();

  /**
   * Sets the SimpleAds title.
   *
   * @param string $title
   *   The SimpleAds title.
   *
   * @return \Drupal\simpleads\Entity\SimpleAdsEntityInterface
   *   The called SimpleAds entity.
   */
  public function setTitle($title);

  /**
   * Gets the SimpleAds creation timestamp.
   *
   * @return int
   *   Creation timestamp of the SimpleAds.
   */
  public function getCreatedTime();

  /**
   * Sets the SimpleAds creation timestamp.
   *
   * @param int $timestamp
   *   The SimpleAds creation timestamp.
   *
   * @return \Drupal\simpleads\Entity\SimpleAdsEntityInterface
   *   The called SimpleAds entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the SimpleAds active status indicator.
   *
   * Unpublished SimpleAds are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the SimpleAds is active.
   */
  public function isActive();

  /**
   * Sets the published status of a SimpleAds.
   *
   * @param bool $status
   *   TRUE to set this SimpleAds to active, FALSE to set it to unpublished.
   *
   * @return \Drupal\simpleads\Entity\SimpleAdsEntityInterface
   *   The called SimpleAds entity.
   */
  public function setActive($status);

  /**
   * Returns the SimpleAds inactive status indicator.
   *
   * @return bool
   *   TRUE if the SimpleAds is inactive.
   */
  public function isInactive();

  /**
   * Sets the inactive status of a SimpleAds.
   *
   * @param bool $inactive
   *   TRUE to mark this ad as inactive.
   *
   * @return \Drupal\simpleads\Entity\SimpleAdsEntityInterface
   *   The called SimpleAds entity.
   */
  public function setInactive($inactive);

  /**
   * Returns group ID.
   *
   * @return int
   *   Group taxonomy ID.
   */
  public function getGroup();

  /**
   * Sets group ID of a SimpleAds.
   *
   * @param int $target_id
   *   Group taxonomy term ID.
   *
   * @return \Drupal\simpleads\Entity\SimpleAdsEntityInterface
   *   The called SimpleAds entity.
   */
  public function setGroup($target_id);

  /**
   * Returns the SimpleAds campagin.
   *
   * @return int
   *   Taxonomy campaign ID.
   */
  public function getCampaign();

  /**
   * Sets campagin ID of a SimpleAds.
   *
   * @param int $target_id
   *   Campaign taxonomy term ID.
   *
   * @return \Drupal\simpleads\Entity\SimpleAdsEntityInterface
   *   The called SimpleAds entity.
   */
  public function setCampaign($target_id);

  /**
   * Gets the SimpleAds start date.
   *
   * @return date
   *   Start date of the SimpleAds.
   */
  public function getStartDate();

  /**
   * Sets the SimpleAds start date.
   *
   * @param date $date
   *   The SimpleAds start date.
   *
   * @return \Drupal\simpleads\Entity\SimpleAdsEntityInterface
   *   The called SimpleAds entity.
   */
  public function setStartDate($date);

  /**
   * Gets the SimpleAds current date.
   *
   * @return date
   *   Current date of the SimpleAds.
   */
  public function getCurrentDate();

  /**
   * Gets the SimpleAds end date.
   *
   * @return date
   *   Start date of the SimpleAds.
   */
  public function getEndDate();

  /**
   * Sets the SimpleAds end date.
   *
   * @param date $date
   *   The SimpleAds end date.
   *
   * @return \Drupal\simpleads\Entity\SimpleAdsEntityInterface
   *   The called SimpleAds entity.
   */
  public function setEndDate($date);

  /**
   * Gets the SimpleAds type.
   *
   * @return string
   *   Return SimpleAds type.
   */
  public function getType();

  /**
   * Gets the SimpleAds statistics.
   *
   * @return array
   *   Return SimpleAds statistics.
   */
  public function getStatistics();

  /**
   * Gets the SimpleAds clicks.
   *
   * @return int
   *   Return SimpleAds clicks value.
   */
  public function getClicks();

  /**
   * Gets the SimpleAds impressions.
   *
   * @return int
   *   Return SimpleAds impressions value.
   */
  public function getImpressions();

  /**
   * Sets the SimpleAds image.
   *
   * @param int $target_id
   *   File entity target ID.
   *
   * @return \Drupal\simpleads\Entity\SimpleAdsEntityInterface
   *   The called SimpleAds entity.
   */
  public function setImage($traget_id);

  /**
   * Gets the SimpleAds image.
   *
   * @return \Drupal\file\Entity\File
   *   Returns File entity.
   */
  public function getImage();

  /**
   * Sets the SimpleAds responsive desktop image.
   *
   * @param int $target_id
   *   File entity target ID.
   *
   * @return \Drupal\simpleads\Entity\SimpleAdsEntityInterface
   *   The called SimpleAds entity.
   */
  public function setResponsiveImageDesktop($traget_id);

  /**
   * Gets the SimpleAds responsive desktop image.
   *
   * @return \Drupal\file\Entity\File
   *   Returns File entity.
   */
  public function getResponsiveImageDesktop();

  /**
   * Sets the SimpleAds responsive tablet image.
   *
   * @param int $target_id
   *   File entity target ID.
   *
   * @return \Drupal\simpleads\Entity\SimpleAdsEntityInterface
   *   The called SimpleAds entity.
   */
  public function setResponsiveImageTablet($traget_id);

  /**
   * Gets the SimpleAds responsive tablet image.
   *
   * @return \Drupal\file\Entity\File
   *   Returns File entity.
   */
  public function getResponsiveImageTablet();

  /**
   * Sets the SimpleAds responsive mobile image.
   *
   * @param int $target_id
   *   File entity target ID.
   *
   * @return \Drupal\simpleads\Entity\SimpleAdsEntityInterface
   *   The called SimpleAds entity.
   */
  public function setResponsiveImageMobile($traget_id);

  /**
   * Gets the SimpleAds responsive mobile image.
   *
   * @return \Drupal\file\Entity\File
   *   Returns File entity.
   */
  public function getResponsiveImageMobile();

  /**
   * Sets the SimpleAds HTML5.
   *
   * @param int $target_id
   *   File entity target ID.
   *
   * @return \Drupal\simpleads\Entity\SimpleAdsEntityInterface
   *   The called SimpleAds entity.
   */
  public function setHtml5($traget_id);

  /**
   * Gets the SimpleAds HTML5.
   *
   * @return \Drupal\file\Entity\File
   *   Returns File entity.
   */
  public function getHtml5();

  /**
   * Gets the SimpleAds HTML5 unzipped path to index file.
   *
   * @return string
   *   Relative path to the iframe index file.
   */
  public function getHtml5IndexPath();

  /**
   * Sets the SimpleAds advertisement URL.
   *
   * @param string $url
   *   Advertisement URL.
   *
   * @return \Drupal\simpleads\Entity\SimpleAdsEntityInterface
   *   The called SimpleAds entity.
   */
  public function setUrl($url);

  /**
   * Gets the SimpleAds advertisement URL.
   *
   * @return string
   *   Returns advertisement URL.
   */
  public function getUrl();

  /**
   * Sets the URL target status.
   *
   * @param bool $new_window
   *   TRUE if the SimpleAds advertisement will be open in a new window.
   *
   * @return \Drupal\simpleads\Entity\SimpleAdsEntityInterface
   *   The called SimpleAds entity.
   */
  public function setUrlOpenNewWindow($new_window);

  /**
   * Returns the SimpleAds URL target status.
   *
   * @return bool
   *   TRUE if the SimpleAds advertisement will be open in a new window.
   */
  public function isUrlOpenWindow();

}
