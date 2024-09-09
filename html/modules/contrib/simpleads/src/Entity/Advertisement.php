<?php

namespace Drupal\simpleads\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;
use Drupal\link\LinkItemInterface;
use Drupal\file\Entity\File;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Url;
use Drupal\simpleads\SimpleAdsStats;

/**
 * Defines the Advertisement entity.
 *
 * @ingroup simpleads
 *
 * @ContentEntityType(
 *   id = "simpleads",
 *   label = @Translation("SimpleAds"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\simpleads\Entity\ListBuilder\AdvertisementListBuilder",
 *     "views_data" = "Drupal\simpleads\Entity\ViewsData\AdvertisementViewsData",
 *     "form" = {
 *       "default" = "Drupal\simpleads\Entity\Form\AdvertisementForm",
 *       "add" = "Drupal\simpleads\Entity\Form\AdvertisementForm",
 *       "edit" = "Drupal\simpleads\Entity\Form\AdvertisementForm",
 *       "delete" = "Drupal\simpleads\Entity\Form\AdvertisementDeleteForm",
 *     },
 *     "access" = "Drupal\simpleads\Entity\AccessControlHandler\AdvertisementAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\simpleads\Routing\SimpleAdsHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "simpleads",
 *   admin_permission = "administer simpleads entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" = "/admin/content/simpleads/{simpleads}",
 *     "add-form" = "/admin/content/simpleads/add",
 *     "edit-form" = "/admin/content/simpleads/{simpleads}/edit",
 *     "delete-form" = "/admin/content/simpleads/{simpleads}/delete",
 *     "statistics" = "/admin/content/simpleads/{simpleads}/stats",
 *     "collection" = "/admin/content/simpleads",
 *   },
 *   field_ui_base_route = "simpleads.advertisement"
 * )
 */
class Advertisement extends ContentEntityBase implements AdvertisementInterface {

  use EntityChangedTrait;

  const HTML5_PATH = 'public://simpleads/html5';

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    parent::preCreate($storage, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    if ($campaign = $this->getCampaign()) {
      $this->setStartDate(NULL);
      if ($campaign->isActive() && $campaign->isRunning($this)) {
        $this->setActive(TRUE);
      }
      else {
        $this->setActive(FALSE);
      }
    }
    else {
      // Checking ad dates and settings status to active/inactive based on date comparison.
      $current_date = $this->getCurrentDate();
      $start_date = $this->getStartDate();
      $end_date = $this->getEndDate();
      // Make sure the ad is published when start date is equial or earlier compared to current.
      if ($start_date <= $current_date) {
        $this->setActive(TRUE);
      }
      // Make sure the ad is unpublished if start date is in the future.
      if ($start_date > $current_date) {
        $this->setActive(FALSE);
      }
      if (!empty($end_date) && $end_date <= $current_date) {
        $this->setActive(FALSE);
      }
    }
    if ($this->getType() == 'html5') {
      if ($zip = $this->getHtml5()) {
        $this->extractZipFile($zip);
      }
    }
    parent::preSave($storage);
    // Make sure to invalidate cache and make sure Rest endpoint is serving the most recent ads.
    Cache::invalidateTags(['simpleads_group']);
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->get('title')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle($title) {
    $this->set('title', $title);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isActive() {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setActive($status) {
    $this->set('status', $status ? TRUE : FALSE);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isInactive() {
    return (bool) $this->getEntityKey('inactive');
  }

  /**
   * {@inheritdoc}
   */
  public function setInactive($inactive) {
    $this->set('inactive', $inactive ? TRUE : FALSE);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroup() {
    return $this->get('group')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setGroup($target_id) {
    $this->set('group', $target_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCampaign() {
    return $this->get('campaign')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setCampaign($target_id) {
    $this->set('campaign', $target_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStartDate() {
    if ($start_date = $this->get('start_date')->value) {
      return $this->convertFromUTC($start_date);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setStartDate($date) {
    $this->set('start_date', $date);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEndDate() {
    if ($end_date = $this->get('end_date')->value) {
      return $this->convertFromUTC($end_date);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentDate() {
    return $this->convertFromUTC('now');
  }

  /**
   * {@inheritdoc}
   */
  public function setEndDate($date) {
    $this->set('end_date', $date);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return $this->get('type')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatistics() {
    return \Drupal::service('simpleads.stats')->setEntityId($this->id())->loadAll();
  }

  /**
   * {@inheritdoc}
   */
  public function getClicks($todayOnly = FALSE) {
    return \Drupal::service('simpleads.stats')->setEntityId($this->id())->getClicks($todayOnly);
  }

  /**
   * {@inheritdoc}
   */
  public function getImpressions($todayOnly = FALSE) {
    return \Drupal::service('simpleads.stats')->setEntityId($this->id())->getImpressions($todayOnly);
  }

  /**
   * {@inheritdoc}
   */
  public function setResponsiveImageDesktop($traget_id) {
    $this->set('responsive_image_desktop', $traget_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponsiveImageDesktop() {
    return $this->loadFileField('responsive_image_desktop');
  }

  /**
   * {@inheritdoc}
   */
  public function setResponsiveImageTablet($traget_id) {
    $this->set('responsive_image_tablet', $traget_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponsiveImageTablet() {
    return $this->loadFileField('responsive_image_tablet');
  }

  /**
   * {@inheritdoc}
   */
  public function setResponsiveImageMobile($traget_id) {
    $this->set('responsive_image_mobile', $traget_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponsiveImageMobile() {
    return $this->loadFileField('responsive_image_mobile');
  }

  /**
   * {@inheritdoc}
   */
  public function setImage($traget_id) {
    $this->set('image', $traget_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getImage() {
    return $this->loadFileField('image');
  }

  /**
   * {@inheritdoc}
   */
  public function setHtml5($traget_id) {
    $this->set('html5', $traget_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHtml5() {
    return $this->loadFileField('html5');
  }

  /**
   * {@inheritdoc}
   */
  public function getHtml5IndexPath() {
    if ($file = $this->getHtml5()) {
      $uri = static::HTML5_PATH . '/' . $file['file']->id() . '/index.html';
      return \Drupal::service('file_url_generator')->generateAbsoluteString($uri);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl() {
    if ($url = $this->get('url')->uri) {
      if (substr($url, 0, 1) == '/') {
        $url = Url::fromUserInput($url, ['absolute' => TRUE])->toString();
      }
      return $url;
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function setUrl($url) {
    $this->set('url', $url);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setUrlOpenNewWindow($new_window) {
    $this->set('url_target', $new_window ? TRUE : FALSE);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isUrlOpenWindow() {
    return (bool) $this->get('url_target')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the SimpleAds entity.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Advertisement Title'))
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['group'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Advertisement Group'))
      ->setSetting('target_type', 'simpleads_group')
      ->setSetting('handler', 'default:simpleads_group')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'label' => 'above',
        'type' => 'options_select',
        'weight' => -3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Advertisement Type'))
      ->setSetting('allowed_values_function', 'simpleads_advertisement_types')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'options_select',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDefaultValue('image')
      ->setRequired(TRUE);

    $fields['image'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Image Advertisement'))
      ->setSettings([
        'file_directory' => 'simpleads/image',
        'alt_field_required' => FALSE,
        'title_field_required' => FALSE,
        'title_field' => TRUE,
        'file_extensions' => 'png jpg jpeg gif webp',
      ])
     ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'image',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'label' => 'hidden',
        'type' => 'image_image',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(FALSE);

    $fields['responsive_image_desktop'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Desktop Advertisement'))
      ->setDescription(t('This advertisement appears on Desktop devices.'))
      ->setSettings([
        'file_directory' => 'simpleads/responsive',
        'alt_field_required' => FALSE,
        'title_field_required' => FALSE,
        'title_field' => TRUE,
        'file_extensions' => 'png jpg jpeg gif webp',
      ])
     ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'image',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'label' => 'hidden',
        'type' => 'image_image',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(FALSE);

    $fields['responsive_image_tablet'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Tablet Advertisement'))
      ->setDescription(t('This advertisement appears on Tablet devices'))
      ->setSettings([
        'file_directory' => 'simpleads/responsive',
        'alt_field_required' => FALSE,
        'title_field_required' => FALSE,
        'title_field' => TRUE,
        'file_extensions' => 'png jpg jpeg gif webp',
      ])
     ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'image',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'label' => 'hidden',
        'type' => 'image_image',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(FALSE);

    $fields['responsive_image_mobile'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Mobile Advertisement'))
      ->setDescription(t('This advertisement appears on Mobile devices'))
      ->setSettings([
        'file_directory' => 'simpleads/responsive',
        'alt_field_required' => FALSE,
        'title_field_required' => FALSE,
        'title_field' => TRUE,
        'file_extensions' => 'png jpg jpeg gif webp',
      ])
     ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'image',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'label' => 'hidden',
        'type' => 'image_image',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(FALSE);

    $fields['html5'] = BaseFieldDefinition::create('file')
      ->setLabel(t('HTML5 Advertisement'))
      ->setDescription(t('HTML5 Advertisement (make sure ZIP file contains index.html). Changing ad title after file upload could break the ad.'))
      ->setSetting('file_extensions', 'zip')
      ->setSetting('file_directory', 'simpleads/html5')
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'file_link',
        'weight' => 0,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'file_generic',
        'weight' => 0,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['campaign'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Advertisement Campaign'))
      ->setDescription(t('Advertisement Campaign.'))
      ->setSetting('target_type', 'simpleads_campaign')
      ->setSetting('handler', 'default:simpleads_campaign')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'label' => 'above',
        'type' => 'options_select',
        'weight' => 12,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['start_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Start Date'))
      ->setDescription(t('Advertisement Start Date.'))
      ->setSettings([
        'datetime_type' => 'datetime'
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'datetime_default',
        'settings' => [
          'format_type' => 'medium',
        ],
        'weight' => 14,
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
        'weight' => 14,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDefaultValue([
        'default_date_type' => 'now',
        'default_date' => 'now',
      ]);

    $fields['end_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('End Date'))
      ->setDescription(t('Advertisement End Date.'))
      ->setSettings([
        'datetime_type' => 'datetime'
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'datetime_default',
        'settings' => [
          'format_type' => 'medium',
        ],
        'weight' => 15,
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_default',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['url'] = BaseFieldDefinition::create('link')
      ->setLabel(t('URL'))
      ->setDescription(t('Advertisement URL.'))
      ->setSettings([
        'link_type' => LinkItemInterface::LINK_GENERIC,
        'title' => DRUPAL_DISABLED,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'link',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'link_default',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setRequired(TRUE);

    $fields['url_target'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Open URL in a new window'))
      ->setDescription(t('When visitor clicks on this advertisement it will be open in a new browser window.'))
      ->setSetting('on_label', t('Yes'))
      ->setSetting('off_label', t('No'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDefaultValue(TRUE);

    $fields['inactive'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Temporarily disable this ad'))
      ->setDescription(t('This option will override active status and make an ad inactive.'))
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 100,
      ])
      ->setDefaultValue(FALSE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDescription(t('A boolean indicating whether this advertisement is active.'))
      ->setSetting('on_label', t('Active'))
      ->setSetting('off_label', t('Inactive'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 101,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDefaultValue(TRUE);

    $fields['stats'] = BaseFieldDefinition::create('simpleads_stats')
      ->setLabel(t('Statistics'))
      ->setDescription(t('SimpleAds statistics'))
      ->setClass('\Drupal\simpleads\Plugin\Field\SimpleAdsFieldItem')
      ->setComputed(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'simpleads_stats',
        'weight' => 102,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['advertisement'] = BaseFieldDefinition::create('simpleads_advertisement')
      ->setLabel(t('Rendered Advertisement'))
      ->setDescription(t('Rendered SimpleAds advertisement'))
      ->setClass('\Drupal\simpleads\Plugin\Field\SimpleAdsFieldItem')
      ->setComputed(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'simpleads_advertisement',
        'weight' => 103,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

  /**
   * Convert UTC datetime to date with current timezone.
   *
   * @param $date_string
   * @return \DateTime
   */
  protected function convertFromUTC($date_string) {
    $date = new \DateTime($date_string, new \DateTimeZone('UTC') );
    $date->setTimeZone(new \DateTimeZone(date_default_timezone_get()));
    return $date;
  }

  /**
   * Load file reference field.
   *
   * @param $field_name
   * @return array
   */
  protected function loadFileField($field_name) {
    if ($field = $this->get($field_name)) {
      if (empty($field->target_id)) {
        return;
      }
      return [
        'file' => File::load($field->target_id),
        'prop'  => [
          'alt' => Html::escape($field->alt),
          'title' => Html::escape($field->title),
        ],
      ];
    }
  }

  /**
   * Extract from Zip file.
   */
  protected function extractZipFile($file) {
    if (function_exists('archiver_get_archiver')) {
      if ($archiver = archiver_get_archiver($file['file']->getFileUri())) {
        $archiver->extract(static::HTML5_PATH . '/' . $file['file']->id());
      }
    }
    else {
      $archiver = \Drupal::service('plugin.manager.archiver')->getInstance([
        'filepath' => \Drupal::service('file_system')->realpath($file['file']->getFileUri()),
      ]);
      $archiver->extract(static::HTML5_PATH . '/' . $file['file']->id());
    }
  }

}
