<?php

namespace Drupal\simpleads\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\NumericFilter;
use Drupal\Component\Utility\Html;

/**
 * SimpleAds base entity reference exposed filter.
 */
class SimpleAdsViewFilter extends NumericFilter {

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    if ($this->isAGroup()) {
      return $this->t('grouped');
    }
    if (!empty($this->options['exposed'])) {
      return $this->t('exposed');
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    if ($this->value != 'All') {
      $field = "$this->tableAlias.$this->realField";

      $info = $this->operators();
      if (!empty($info[$this->operator]['method'])) {
        $this->{$info[$this->operator]['method']}($field);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opSimple($field) {
    if (!empty($this->value['value']) && $this->value['value'] != 'All') {
      $this->query->addWhere($this->options['group'], $field, $this->value['value'], $this->operator);
    }
    else {
      if (!empty($this->value) && !is_array($this->value)) {
        $this->query->addWhere($this->options['group'], $field, $this->value, $this->operator);
      }
    }
  }

  /**
   * Get select field option values.
   */
  protected function getReferenceOptions($type) {
    $options = [];
    $entities = \Drupal::entityTypeManager()
      ->getStorage($type)
      ->loadByProperties(['status' => TRUE]);
    foreach ($entities as $entity) {
      $options[$entity->id()] = Html::decodeEntities($entity->getName());
    }
    return $options;
  }

}
