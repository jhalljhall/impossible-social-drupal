<?php

namespace Drupal\simpleads\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;

/**
 * Provides a 'SimpleAdsBlock' block.
 *
 * @Block(
 *  id = "simpleads",
 *  admin_label = @Translation("SimpleAds"),
 * )
 */
class SimpleAdsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'group'                 => '',
      'rotation_impressions'  => TRUE,
      'rotation'              => 'loop',
      'rotation_speed'        => 900,
      'rotation_pauseonhover' => FALSE,
      'multiple_random_limit' => 3,
      'show_in_modal'         => FALSE,
      'modal_delay_type'      => 'page_visits',
      'modal_delay'           => 10, // 10 seconds
      'modal_page_visits'     => 5,
      'modal_visits_timeout'  => 2, // 2 hours
      'node_ref_field'        => '',
      'simpleads_ref_field'   => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['group'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Advertisement group'),
      '#description'   => $this->t('Advertisement group to display in this block.'),
      '#options'       => $this->getAllGroups(),
      '#default_value' => $this->configuration['group'],
      '#required'      => TRUE,
    ];
    $form['rotation'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Display'),
      '#description'   => $this->t('Advertisement display mode.'),
      '#options'       => [
        'loop'    => $this->t('Loop'),
        'multiple'    => $this->t('Show multiple random'),
        'refresh' => $this->t('Random on every page refresh'),
      ],
      '#default_value' => $this->configuration['rotation'],
    ];
    $form['rotation_speed'] = [
      '#type' => 'number',
      '#title' => $this->t('Transition (rotation) speed'),
      '#default_value' => $this->configuration['rotation_speed'],
      '#states' => [
        'visible' => [
          ':input[name="settings[rotation]"]' => ['value' => 'loop']
        ]
      ],
    ];
    $form['rotation_pauseonhover'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Pause autoplay on mouse hover'),
      '#default_value' => $this->configuration['rotation_pauseonhover'],
      '#states' => [
        'visible' => [
          ':input[name="settings[rotation]"]' => ['value' => 'loop']
        ]
      ],
    ];
    $form['rotation_impressions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Count impressions on each rotation once'),
      '#default_value' => $this->configuration['rotation_impressions'],
      '#states' => [
        'visible' => [
          ':input[name="settings[rotation]"]' => ['value' => 'loop']
        ]
      ],
    ];
    $form['multiple_random_limit'] = [
      '#type' => 'select',
      '#title' => $this->t('Limit number of ads to show'),
      '#description'   => $this->t('Controls the number of ads to show in a block.'),
      '#options' => array_combine(range(1, 25), range(1, 25)),
      '#default_value' => $this->configuration['multiple_random_limit'],
      '#states' => [
        'visible' => [
          ':input[name="settings[rotation]"]' => ['value' => 'multiple'],
        ]
      ],
    ];
    $form['show_in_modal'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Show advertisements in modal'),
      '#description'   => $this->t('This option will make ads appear in modal.'),
      '#default_value' => $this->configuration['show_in_modal'],
    ];
    $form['modal_delay_type'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Modal Delay'),
      '#description'   => $this->t('How would you like the modal ad to appear.'),
      '#options'       => [
        'page_visits' => $this->t('Page Visits'),
        'delay'       => $this->t('Delay'),
      ],
      '#default_value' => $this->configuration['modal_delay_type'],
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
      '#default_value' => $this->configuration['modal_delay'],
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
      '#default_value' => $this->configuration['modal_page_visits'],
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
      '#default_value' => $this->configuration['modal_visits_timeout'],
      '#states' => [
        'visible' => [
          ':input[name="settings[modal_delay_type]"]' => ['value' => 'page_visits'],
          ':input[name="settings[show_in_modal]"]'    => ['checked' => TRUE]
        ]
      ],
    ];
    $form['reference_fields'] = [
      '#type' => 'details',
      '#title' => $this->t('Reference fields'),
      '#description' => $this->t('Show ads based on relationship between two fields in two entity types. Ads will be shown if specified terms exist in the currently viewing Node'),
      '#open' => FALSE,
    ];
    $form['reference_fields']['node_ref_field'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Node'),
      '#description'   => $this->t('Choose a field that will be used as a reference in SimpleAds.'),
      '#options'       => $this->getReferenceFields('node'),
      '#default_value' => $this->configuration['node_ref_field'],
    ];
    $form['reference_fields']['simpleads_ref_field'] = [
      '#type'          => 'select',
      '#title'         => $this->t('SimpleAds'),
      '#description'   => $this->t('Choose a field that will be used as a reference in selected Nodes.'),
      '#options'       => $this->getReferenceFields('simpleads'),
      '#default_value' => $this->configuration['simpleads_ref_field'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['group'] = $form_state->getValue('group');
    $this->configuration['rotation'] = $form_state->getValue('rotation');
    $this->configuration['rotation_speed'] = $form_state->getValue('rotation_speed');
    $this->configuration['rotation_pauseonhover'] = $form_state->getValue('rotation_pauseonhover');
    $this->configuration['rotation_impressions'] = $form_state->getValue('rotation_impressions');
    $this->configuration['multiple_random_limit'] = $form_state->getValue('multiple_random_limit');
    $this->configuration['show_in_modal'] = $form_state->getValue('show_in_modal');
    $this->configuration['modal_delay_type'] = $form_state->getValue('modal_delay_type');
    $this->configuration['modal_delay'] = $form_state->getValue('modal_delay');
    $this->configuration['modal_page_visits'] = $form_state->getValue('modal_page_visits');
    $this->configuration['modal_visits_timeout'] = $form_state->getValue('modal_visits_timeout');
    // Reference fields
    $values = $form_state->getValues();
    $this->configuration['node_ref_field'] = $values['reference_fields']['node_ref_field'];
    $this->configuration['simpleads_ref_field'] = $values['reference_fields']['simpleads_ref_field'];
    // Make sure we invalidate cache tag for the SimpleAds block.
    Cache::invalidateTags(['simpleads_group_' . $this->configuration['group']]);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['#attached']['library'][] = 'simpleads/simpleads.block.js';
    $build['simpleads'] = [
      '#theme'                 => 'simpleads_advertisement',
      '#group'                 => $this->configuration['group'],
      '#node_ref_field'        => $this->configuration['node_ref_field'],
      '#simpleads_ref_field'   => $this->configuration['simpleads_ref_field'],
      '#rotation_type'         => $this->configuration['rotation'],
      '#multiple_random_limit' => $this->configuration['multiple_random_limit'],
      '#impressions'           => (bool) $this->configuration['rotation_impressions'] ? 'true' : 'false',
      '#show_in_modal'         => $this->configuration['show_in_modal'],
      '#modal_options'         => json_encode([
        'delay_type'     => $this->configuration['modal_delay'],
        'page_visits'    => $this->configuration['modal_page_visits'],
        'visits_timeout' => $this->configuration['modal_visits_timeout'],
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
          'pauseOnHover' => (bool) $this->configuration['rotation_pauseonhover'],
          'speed'        => (int) $this->configuration['rotation_speed'],
          'infinite'     => TRUE,
        ]),
      ],
      '#cache' => [
        'tags'    => ['simpleads_group_' . $this->configuration['group'], 'simpleads_group'],
        'context' => ['url.query_args'],
      ],
    ];
    return $build;
  }

  /**
   * Get all groups.
   */
  protected function getAllGroups() {
    $groups_data = [];
    $groups = \Drupal::entityTypeManager()
      ->getStorage('simpleads_group')
      ->loadByProperties(['status' => TRUE]);
    foreach ($groups as $group) {
      $groups_data[$group->id()] = Html::decodeEntities($group->getName());
    }
    return $groups_data;
  }

  /**
   * Get entity refernce fields.
   */
  protected function getReferenceFields($entity_type) {
    $fields = [$this->t('- N/A -')];
    $ref_fields = \Drupal::entityTypeManager()->getStorage('field_storage_config')->loadByProperties([
      'entity_type' => $entity_type,
      'type' => 'entity_reference',
      'deleted' => FALSE,
      'status' => 1,
    ]);
    foreach ($ref_fields as $field) {
      $fields[$field->getName()] = $field->getName();
    }
    return $fields;
  }

}
