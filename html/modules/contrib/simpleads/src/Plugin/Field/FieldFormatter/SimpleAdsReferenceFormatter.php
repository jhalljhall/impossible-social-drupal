<?php

namespace Drupal\simpleads\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'simpleads_reference' formatter.
 *
 * @FieldFormatter(
 *   id = "simpleads_reference",
 *   label = @Translation("Reference"),
 *   field_types = {
 *     "simpleads_reference"
 *   }
 * )
 */
class SimpleAdsReferenceFormatter extends FormatterBase {

  /**
  * {@inheritdoc}
  */
   public static function defaultSettings() {
    return [
      'rotation_impressions'  => TRUE,
      'rotation'              => 'loop',
      'rotation_speed'        => 900,
      'rotation_pauseonhover' => FALSE,
      'show_in_modal'         => FALSE,
      'modal_delay_type'      => 'page_visits',
      'modal_delay'           => 10, // 10 seconds
      'modal_page_visits'     => 5,
      'modal_visits_timeout'  => 2, // 2 hours
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm($form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $form['rotation'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Display'),
      '#description'   => $this->t('Advertisement display mode.'),
      '#options'       => [
        'loop'    => $this->t('Loop'),
        'refresh' => $this->t('Random on every page refresh'),
      ],
      '#default_value' => $this->getSetting('rotation'),
    ];
    $form['rotation_speed'] = [
      '#type' => 'number',
      '#title' => $this->t('Transition (rotation) speed'),
      '#default_value' => $this->getSetting('rotation_speed'),
      '#states' => [
        'visible' => [
          ':input[name="settings[rotation]"]' => ['value' => 'loop']
        ]
      ],
    ];
    $form['rotation_pauseonhover'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Pause autoplay on mouse hover'),
      '#default_value' => $this->getSetting('rotation_pauseonhover'),
      '#states' => [
        'visible' => [
          ':input[name="settings[rotation]"]' => ['value' => 'loop']
        ]
      ],
    ];
    $form['rotation_impressions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Count impressions on each rotation once'),
      '#default_value' => $this->getSetting('rotation_impressions'),
      '#states' => [
        'visible' => [
          ':input[name="settings[rotation]"]' => ['value' => 'loop']
        ]
      ],
    ];
    $form['show_in_modal'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Show advertisements in modal'),
      '#description'   => $this->t('This option will make ads appear in modal.'),
      '#default_value' => $this->getSetting('show_in_modal'),
    ];
    $form['modal_delay_type'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Modal Delay'),
      '#description'   => $this->t('How would you like the modal ad to appear.'),
      '#options'       => [
        'page_visits' => $this->t('Page Visits'),
        'delay'       => $this->t('Delay'),
      ],
      '#default_value' => $this->getSetting('modal_delay_type'),
      '#states' => [
        'visible' => [
          ':input[name="settings[show_in_modal]"]' => ['checked' => TRUE]
        ]
      ],
    ];
    $form['modal_delay'] = [
      '#type' => 'number',
      '#title' => $this->t('Delay appearance'),
      '#description'   => $this->t('Number of seconds before modal will be presented to a visitor.'),
      '#default_value' => $this->getSetting('modal_delay'),
      '#states' => [
        'visible' => [
          ':input[name="settings[modal_delay_type]"]' => ['value' => 'delay'],
          ':input[name="settings[show_in_modal]"]'    => ['checked' => TRUE]
        ]
      ],
    ];
    $form['modal_page_visits'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of page visits'),
      '#description'   => $this->t('Number of page visits before modal will be presented to a visitor.'),
      '#default_value' => $this->getSetting('modal_page_visits'),
      '#states' => [
        'visible' => [
          ':input[name="settings[modal_delay_type]"]' => ['value' => 'page_visits'],
          ':input[name="settings[show_in_modal]"]'    => ['checked' => TRUE]
        ]
      ],
    ];
    $form['modal_visits_timeout'] = [
      '#type' => 'select',
      '#title' => $this->t('Page Visit Timeout Hours'),
      '#description'   => $this->t('Delay in hours before modal will be shown to a visitor again after meeting page visits limit.'),
      '#options' => array_combine(range(0, 24), range(0, 24)),
      '#default_value' => $this->getSetting('modal_visits_timeout'),
      '#states' => [
        'visible' => [
          ':input[name="settings[modal_delay_type]"]' => ['value' => 'page_visits'],
          ':input[name="settings[show_in_modal]"]'    => ['checked' => TRUE]
        ]
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $settings = $this->getSettings();
    $summary[] = $this->t('Rotation type: %type', ['%type' => $settings['rotation']]);
    $summary[] = $this->t('Coun impressions on rotation: %rotation', ['%rotation' => $settings['rotation_impressions'] ? 'Yes' : 'No']);
    $summary[] = $this->t('Rotation speed: %speed', ['%speed' => $settings['rotation_speed']]);
    $summary[] = $this->t('Pause on hover: %rotation_pauseonhover', ['%rotation_pauseonhover' => $settings['rotation_pauseonhover'] ? 'Yes' : 'No']);
    $summary[] = $this->t('Show in modal: %show_in_modal', ['%show_in_modal' => $settings['show_in_modal'] ? 'Yes' : 'No']);
    if ($settings['show_in_modal']) {
      $summary[] = $this->t('Modal delay in seconds: %modal_delay', ['%modal_delay' => $settings['modal_delay']]);
      $summary[] = $this->t('Modal delay type: %modal_delay_type', ['%modal_delay_type' => $settings['modal_delay_type']]);
      $summary[] = $this->t('Number of page visits: %modal_page_visits', ['%modal_page_visits' => $settings['modal_page_visits']]);
      $summary[] = $this->t('Modal visits timeout: %modal_visits_timeout hours', ['%modal_visits_timeout' => $settings['modal_visits_timeout']]);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $entity = $items->getEntity();
    $field_name = $items->getItemDefinition()->getFieldDefinition()->getName();
    return [
      '#theme'            => 'simpleads_reference',
      '#entity_type'      => $entity->getEntityType()->id(),
      '#field_name'       => $field_name,
      '#entity_id'        => $entity->id(),
      '#rotation_type'    => $this->getSetting('rotation'),
      '#impressions'      => (bool) $this->getSetting('rotation_impressions') ? 'true' : 'false',
      '#show_in_modal'    => $this->getSetting('show_in_modal'),
      '#modal_options'    => json_encode([
        'delay_type'     => $this->getSetting('modal_delay'),
        'page_visits'    => $this->getSetting('modal_page_visits'),
        'visits_timeout' => $this->getSetting('modal_visits_timeout'),
      ]),
      '#rotation_options' => [
        // Slick slider options.
        'loop' => json_encode([
          'draggable'    => FALSE,
          'arrows'       => FALSE,
          'dots'         => FALSE,
          'fade'         => TRUE,
          'autoplay'     => TRUE,
          'pauseOnFocus' => FALSE,
          'pauseOnHover' => (bool) $this->getSetting('rotation_pauseonhover'),
          'speed'        => (int) $this->getSetting('rotation_speed'),
          'infinite'     => TRUE,
        ]),
      ],
      '#cache' => [
        'tags'    => ['simpleads_group'],
        'context' => ['url.query_args'],
      ],
      '#attached' => [
        'library' => ['simpleads/simpleads.reference.js'],
      ],
    ];
  }

}
