<?php

namespace Drupal\simpleads\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;
use Drupal\simpleads\Entity\AdvertisementInterface;
use Drupal\Core\Cache\Cache;

/**
 * Defines the Campaign entity.
 *
 * @ingroup simpleads
 *
 * @ContentEntityType(
 *   id = "simpleads_campaign",
 *   label = @Translation("SimpleAds Campaign"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\simpleads\Entity\ListBuilder\CampaignListBuilder",
 *     "views_data" = "Drupal\simpleads\Entity\ViewsData\CampaignViewsData",
 *     "form" = {
 *       "default" = "Drupal\simpleads\Entity\Form\CampaignForm",
 *       "add" = "Drupal\simpleads\Entity\Form\CampaignForm",
 *       "edit" = "Drupal\simpleads\Entity\Form\CampaignForm",
 *       "delete" = "Drupal\simpleads\Entity\Form\CampaignDeleteForm",
 *     },
 *     "access" = "Drupal\simpleads\Entity\AccessControlHandler\CampaignAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\simpleads\Routing\SimpleAdsHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "simpleads_campaign",
 *   admin_permission = "administer simpleads_campaign entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" = "/admin/config/simpleads/campaign/{simpleads_campaign}",
 *     "add-form" = "/admin/config/simpleads/campaign/add",
 *     "edit-form" = "/admin/config/simpleads/campaign/{simpleads_campaign}/edit",
 *     "delete-form" = "/admin/config/simpleads/campaign/{simpleads_campaign}/delete",
 *     "collection" = "/admin/config/simpleads/campaign/list",
 *   },
 *   field_ui_base_route = "simpleads.campaign"
 * )
 */
class Campaign extends ContentEntityBase implements CampaignInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    // Make sure to invalidate cache and make sure Rest endpoint is serving the most recent ads.
    Cache::invalidateTags(['simpleads_group']);
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
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
    $values = [];
    foreach ($this->get('type') as $item) {
      $values[] = $item->value;
    }
    return $values;
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
  public function getClickLimit() {
    return (int) $this->get('click')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getImpressionLimit() {
    return (int) $this->get('impression')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function isWithinDateRange() {
    $start_date = $this->getStartDate();
    $end_date = $this->getEndDate();
    $current_date = $this->getCurrentDate();
    if ($start_date <= $current_date) {
      return TRUE;
    }
    if ($start_date > $current_date) {
      return FALSE;
    }
    if (!empty($end_date) && $end_date <= $current_date) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isRunning(AdvertisementInterface $advertisement) {
    $clicks = $advertisement->getClicks();
    $impressions = $advertisement->getImpressions();
    $type = $this->getType();
    if (in_array('click', $type)) {
      if ($clicks >= $this->getClickLimit()) {
        return FALSE;
      }
    }
    if (in_array('impression', $type)) {
      if ($impressions >= $this->getImpressionLimit()) {
        return FALSE;
      }
    }
    if (in_array('date', $type)) {
      return $this->isWithinDateRange();
    }
    return TRUE;
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

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the Campaign entity.'))
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
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

    $fields['type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Campaign Type'))
      ->setDescription(t('Campaign Type'))
      ->setSetting('allowed_values_function', 'simpleads_campaign_types')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => -3,
      ])
      ->setDisplayOptions('form', [
        'label' => 'above',
        'type' => 'options_buttons',
        'weight' => -3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDefaultValue('click')
      ->setCardinality(-1)
      ->setRequired(TRUE);

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

    $fields['click'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Number of clicks'))
      ->setDescription(t('Advertisements will be disabled when number of clicks specified in this field is reached.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_integer',
        'weight' => 16,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 16,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['impression'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Number of impressions'))
      ->setDescription(t('Advertisements will be disabled when number of impressions specified in this field is reached.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_integer',
        'weight' => 17,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 17,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Active campaign'))
      ->setDescription(t('Is this campaign active.'))
      ->setSetting('on_label', t('Active'))
      ->setSetting('off_label', t('Inactive'))
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 100,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 101,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDefaultValue(TRUE);

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

}
