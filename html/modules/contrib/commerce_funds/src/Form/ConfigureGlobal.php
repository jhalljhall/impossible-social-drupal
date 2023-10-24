<?php

namespace Drupal\commerce_funds\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form to configure global settings.
 */
class ConfigureGlobal extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_funds_configure_global';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'commerce_funds.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('commerce_funds.settings')->get('global');

    $form['global'] = [
      '#type' => 'details',
      '#title' => $this->t('Default forms'),
      '#open' => TRUE,
    ];

    $options = [
      'deposit' => $this->t('Deposit form'),
      'transfer' => $this->t('Transfer form'),
      'escrow' => $this->t('Escrow form'),
      'convert_currencies' => $this->t('Currency converter form'),
      'withdraw' => $this->t('Withdrawal form'),
    ];

    $form['global']['disable_funds_forms'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Disable default forms?'),
      "#description" => $this->t('In case you are using Transaction fields, this will disable the routes such as /user/funds/deposit or /user/funds/transfer etc.'),
      '#default_value' => $config['disable_funds_forms'] ? array_keys(array_filter($config['disable_funds_forms'])) : [],
      '#options' => $options,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->cleanValues()->getValues();
    $this->config('commerce_funds.settings')
      ->set('global.disable_funds_forms', $values['disable_funds_forms'])
      ->save();

    // Rebuild routes.
    \Drupal::service("router.builder")->rebuild();
    // Clear cache.
    \Drupal::service('cache.render')->invalidateAll();

    parent::submitForm($form, $form_state);
  }

}
