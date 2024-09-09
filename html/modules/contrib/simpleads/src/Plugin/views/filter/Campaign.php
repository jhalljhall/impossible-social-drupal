<?php

namespace Drupal\simpleads\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;

/**
 * Campaign reference filter.
 *
 * @ViewsFilter("simpleads_campaign")
 */
class Campaign extends SimpleAdsViewFilter {

  /**
   * Campaign filter.
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    $form['value'] = [
      '#type' => 'select',
      '#title' => $this->t('Campaign'),
      '#options' => $this->getReferenceOptions('simpleads_campaign'),
      '#default_value' => !empty($this->value['value']) ? $this->value['value'] : '',
    ];
  }

}
