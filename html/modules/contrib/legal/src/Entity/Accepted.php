<?php

namespace Drupal\legal\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\legal\AcceptedInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Legal Terms & Conditions accepted entity.
 *
 * @ingroup legal
 *
 * @ContentEntityType(
 *   id = "legal_accepted",
 *   label = @Translation("T&C Accepted"),
 *   base_table = "legal_accepted",
 *   entity_keys = {
 *     "id" = "legal_id",
 *     "label" = "legal_id",
 *     "uuid" = "uuid",
 *   },
 * )
 */
class Accepted extends ContentEntityBase implements AcceptedInterface {

  /**
   * {@inheritdoc}
   *
   * When a new entity instance is added, set the uid entity reference to
   * the current user as the creator of the instance.
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'uid' => \Drupal::currentUser()->id(),
    ];
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
  public function getOwner() {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * Define the field properties here.
   *
   * Field name, type and size determine the table structure.
   *
   * In addition, we can define how the field and its content can be manipulated
   * in the GUI. The behaviour of the widgets used can be determined here.
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    // Standard field, used as unique if primary index.
    $fields['legal_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the Acceptance.'))
      ->setReadOnly(TRUE);

    // Standard field, unique outside of the scope of the current project.
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the acceptance.'))
      ->setReadOnly(TRUE);

    $fields['version'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Version'))
      ->setDescription(t('The version number of the Terms & Conditions.'));

    $fields['revision'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Revision'))
      ->setDescription(t('The revision number of the Terms & Conditions.'));

    $fields['language'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language code'))
      ->setDescription(t('Language code of the T&C accepted.'));

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('The user that accepted the T&Cs.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default');

    $fields['accepted'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('When the Terms & Conditions were accepted.'));

    return $fields;
  }

}
