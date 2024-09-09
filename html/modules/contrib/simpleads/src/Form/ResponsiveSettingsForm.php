<?php

namespace Drupal\simpleads\Form;

use Drupal\simpleads\Form\BaseSettingsForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ResponsiveSettingsForm.
 *
 * @ingroup simpleads
 */
class ResponsiveSettingsForm extends BaseSettingsForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simpleads_responsive_config';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get the working configuration.
    $config = $this->config(static::CONFIG_NAME);

    $form['desktop_media_query'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Desktop media query'),
      '#description'   => $this->t('Specify media query for the @device view.', ['@device' => 'desktop']),
      '#default_value' => $config->get('desktop_media_query'),
    ];

    $form['tablet_media_query'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Tablet media query'),
      '#description'   => $this->t('Specify media query for the @device view.', ['@device' => 'tablet']),
      '#default_value' => $config->get('tablet_media_query'),
    ];

    $form['mobile_media_query'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Mobile media query'),
      '#description'   => $this->t('Specify media query for the @device view.', ['@device' => 'mobile']),
      '#default_value' => $config->get('mobile_media_query'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config(static::CONFIG_NAME)
      ->set('desktop_media_query', $form_state->getValue('desktop_media_query'))
      ->set('tablet_media_query', $form_state->getValue('tablet_media_query'))
      ->set('mobile_media_query', $form_state->getValue('mobile_media_query'))
      ->save();
  }

}
