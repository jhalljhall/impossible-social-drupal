<?php

namespace Drupal\simpleads\Form;

use Drupal\simpleads\Form\BaseSettingsForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Class StatisticSettingsForm.
 *
 * @ingroup simpleads
 */
class StatisticSettingsForm extends BaseSettingsForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simpleads_stats_config';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get the working configuration.
    $config = $this->config(static::CONFIG_NAME);

    // Get view mode options from the repository service.
    $options = $this->entity_display_repository->getViewModeOptionsByBundle('simpleads', 'simpleads');

    $form['stats_view_mode'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Statistics view mode'),
      '#description'   => $this->t('Select the view mode to use for displaying statistics.'),
      '#options'       => $options,
      '#default_value' => $config->get('stats_view_mode'),
    ];

    $form['stats_date_format'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Date format'),
      '#description'   => $this->t('Statistics page date format. See <a href="@link">Date and time formats.</a>', [
        '@link' => Url::fromRoute('entity.date_format.collection')->toString(),
      ]),
      '#options'       => $this->getDateFormats(),
      '#default_value' => $config->get('stats_date_format'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config(static::CONFIG_NAME)
      ->set('stats_view_mode', $form_state->getValue('stats_view_mode'))
      ->set('stats_date_format', $form_state->getValue('stats_date_format'))
      ->save();
  }

  /**
   * Get all date formats.
   */
  protected function getDateFormats() {
    $formats = [];
    $entities = \Drupal::entityTypeManager()
      ->getStorage('date_format')
      ->loadMultiple();
    foreach ($entities as $format) {
      $formats[$format->id()] = $format->label();
    }
    return $formats;
  }

}
