<?php

namespace Drupal\simpleads\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;

/**
 * Group reference filter.
 *
 * @ViewsFilter("simpleads_group")
 */
class Group extends SimpleAdsViewFilter {

  /**
   * Group filter.
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    $form['value'] = [
      '#type' => 'select',
      '#title' => $this->t('Group'),
      '#options' => $this->getReferenceOptions('simpleads_group'),
      '#default_value' => !empty($this->value['value']) ? $this->value['value'] : '',
    ];
  }

}
