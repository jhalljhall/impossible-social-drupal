<?php

namespace Drupal\simpleads\Entity\ParamConverter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\ParamConverter\EntityConverter;
use Drupal\Core\ParamConverter\ParamConverterInterface;
use Symfony\Component\Routing\Route;

/**
 * Parameter converter for upcasting SimpleAds advertisement entity.
 */
class AdvertisementConverter extends EntityConverter implements ParamConverterInterface {

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $entity_type_id = $this->getEntityTypeFromDefaults($definition, $name, $defaults);
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $entity_definition = $this->entityTypeManager->getDefinition($entity_type_id);

    // Get the SimpleAds advertisement ID.
    $param_entity_id = $defaults['simpleads'] ?? FALSE;

    if (!$param_entity_id || !($entity = $storage->load($param_entity_id, $value))) {
      throw new \InvalidArgumentException('Unable to load SimpleAds advertisement entity.');
    }

    // If the entity type is revisionable and the parameter has the
    // "load_latest_revision" flag, load the latest revision.
    if ($entity instanceof RevisionableInterface && !empty($definition['load_latest_revision']) && $entity_definition->isRevisionable()) {
      // Retrieve the latest revision ID taking translations into account.
      $langcode = $this->languageManager()
        ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
        ->getId();
      $entity = $this->getLatestTranslationAffectedRevision($entity, $langcode);
    }

    // If the entity type is translatable, ensure we return the proper
    // translation object for the current context.
    if ($entity instanceof EntityInterface && $entity instanceof TranslatableInterface) {
      $entity = $this->entityRepository->getTranslationFromContext($entity, NULL, ['operation' => 'entity_upcast']);
    }

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    // This only applies to simpleads entities.
    return (parent::applies($definition, $name, $route) && $definition['type'] === 'entity:simpleads');
  }

}
