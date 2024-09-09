<?php

namespace Drupal\simpleads\Plugin\views\style;

use Drupal\core\form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * Advertisement view style plugin.
 *
 * @ViewsStyle(
 *   id = "simpleads",
 *   title = @Translation("SimpleAds"),
 *   help = @Translation("Render advertisement entities as ad blocks."),
 *   theme = "views_view_simpleads",
 *   display_types = { "normal" }
 * )
 */
class SimpleAds extends StylePluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['rotation_impressions'] = ['default' => TRUE];
    $options['rotation'] = ['default' => 'loop'];
    $options['rotation_speed'] = ['default' => 900];
    $options['rotation_pauseonhover'] = ['default' => FALSE];
    $options['multiple_random_limit'] = ['default' => 3];
    $options['show_in_modal'] = ['default' => FALSE];
    $options['modal_delay_type'] = ['default' => 'page_visits'];
    $options['modal_delay'] = ['default' => 10]; // 10 seconds
    $options['modal_page_visits'] = ['default' => 5];
    $options['modal_visits_timeout'] = ['default' => 2]; // 2 hours
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    unset($form['uses_fields']);
    $form['rotation'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Display'),
      '#description'   => $this->t('Advertisement display mode.'),
      '#options'       => [
        'loop'    => $this->t('Loop'),
        'multiple'    => $this->t('Show multiple random'),
        'refresh' => $this->t('Random on every page refresh'),
      ],
      '#default_value' => !empty($this->options['rotation']) ? $this->options['rotation'] : 'loop',
    ];
    $form['rotation_speed'] = [
      '#type' => 'number',
      '#title' => $this->t('Transition (rotation) speed'),
      '#default_value' => !empty($this->options['rotation_speed']) ? $this->options['rotation_speed'] : 900,
      '#states' => [
        'visible' => [
          'select[name="style_options[rotation]"]' => ['value' => 'loop']
        ]
      ],
    ];
    $form['rotation_pauseonhover'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Pause autoplay on mouse hover'),
      '#default_value' => !empty($this->options['rotation_pauseonhover']) ? $this->options['rotation_pauseonhover'] : FALSE,
      '#states' => [
        'visible' => [
          'select[name="style_options[rotation]"]' => ['value' => 'loop']
        ]
      ],
    ];
    $form['rotation_impressions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Count impressions on each rotation once'),
      '#default_value' => !empty($this->options['rotation_impressions']) ? $this->options['rotation_impressions'] : TRUE,
      '#states' => [
        'visible' => [
          'select[name="style_options[rotation]"]' => ['value' => 'loop']
        ]
      ],
    ];
    $form['multiple_random_limit'] = [
      '#type' => 'select',
      '#title' => $this->t('Limit number of ads to show'),
      '#description'   => $this->t('Controls the number of ads to show in a block.'),
      '#options' => array_combine(range(1, 25), range(1, 25)),
      '#default_value' => !empty($this->options['multiple_random_limit']) ? $this->options['multiple_random_limit'] : 3,
      '#states' => [
        'visible' => [
          'select[name="style_options[rotation]"]' => ['value' => 'multiple'],
        ]
      ],
    ];
    $form['show_in_modal'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Show advertisements in modal'),
      '#description'   => $this->t('This option will make ads appear in modal.'),
      '#default_value' => !empty($this->options['show_in_modal']) ? $this->options['show_in_modal'] : FALSE,
    ];
    $form['modal_delay_type'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Modal Delay'),
      '#description'   => $this->t('How would you like the modal ad to appear.'),
      '#options'       => [
        'page_visits' => $this->t('Page Visits'),
        'delay'       => $this->t('Delay'),
      ],
      '#default_value' => !empty($this->options['modal_delay_type']) ? $this->options['modal_delay_type'] : 'page_visits',
      '#states' => [
        'visible' => [
          ':input[name="style_options[show_in_modal]"]' => ['checked' => TRUE]
        ]
      ],
    ];
    $form['modal_delay'] = [
      '#type' => 'number',
      '#title' => $this->t('Delay appearance'),
      '#description'   => $this->t('Number of seconds before modal will be presented to a visitor.'),
      '#default_value' => !empty($this->options['modal_delay']) ? $this->options['modal_delay'] : 10,
      '#states' => [
        'visible' => [
          'select[name="style_options[modal_delay_type]"]' => ['value' => 'delay'],
          ':input[name="style_options[show_in_modal]"]'    => ['checked' => TRUE]
        ]
      ],
    ];
    $form['modal_page_visits'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of page visits'),
      '#description'   => $this->t('Number of page visits before modal will be presented to a visitor.'),
      '#default_value' => !empty($this->options['modal_page_visits']) ? $this->options['modal_page_visits'] : 5,
      '#states' => [
        'visible' => [
          ':input[name="style_options[modal_delay_type]"]' => ['value' => 'page_visits'],
          ':input[name="style_options[show_in_modal]"]'    => ['checked' => TRUE]
        ]
      ],
    ];
    $form['modal_visits_timeout'] = [
      '#type' => 'select',
      '#title' => $this->t('Page Visit Timeout Hours'),
      '#description'   => $this->t('Delay in hours before modal will be shown to a visitor again after meeting page visits limit.'),
      '#options' => array_combine(range(0, 24), range(0, 24)),
      '#default_value' => !empty($this->options['modal_visits_timeout']) ? $this->options['modal_visits_timeout'] : 2,
      '#states' => [
        'visible' => [
          'select[name="style_options[modal_delay_type]"]' => ['value' => 'page_visits'],
          ':input[name="style_options[show_in_modal]"]'    => ['checked' => TRUE]
        ]
      ],
    ];
  }

}
