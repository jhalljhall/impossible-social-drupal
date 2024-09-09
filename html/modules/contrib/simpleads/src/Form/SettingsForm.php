<?php

namespace Drupal\simpleads\Form;

use Drupal\simpleads\Form\BaseSettingsForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class SettingsForm.
 *
 * @ingroup simpleads
 */
class SettingsForm extends BaseSettingsForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simpleads_config';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get the working configuration.
    $config = $this->config(static::CONFIG_NAME);

    // Get view mode options from the repository service.
    $options = $this->entity_display_repository->getViewModeOptionsByBundle('simpleads', 'simpleads');

    $form['ads_view_mode'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Advertisement view mode'),
      '#description'   => $this->t('Select the view mode to use for displaying advertisements.'),
      '#options'       => $options,
      '#default_value' => $config->get('ads_view_mode'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config(static::CONFIG_NAME)
      ->set('ads_view_mode', $form_state->getValue('ads_view_mode'))
      ->save();
  }

}
