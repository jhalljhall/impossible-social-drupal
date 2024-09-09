<?php

namespace Drupal\simpleads;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\simpleads\Form\BaseSettingsForm;

/**
 * SimpleAds module helper.
 *
 * @ingroup simpleads
 */
class SimpleAdsModule {

  use StringTranslationTrait;

  /**
   * The module handler to invoke the alter hook.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a \Drupal\simpleads\SimpleAdsModule object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * Implements hook_theme().
   */
  public function hook_theme() {
    $theme = [
      'simpleads_advertisement' => [
        'variables' => [
          'group'                 => NULL,
          'rotation_type'         => NULL,
          'multiple_random_limit' => NULL,
          'rotation_options'      => NULL,
          'impressions'           => NULL,
          'show_in_modal'         => NULL,
          'modal_options'         => NULL,
          'node_ref_field'        => NULL,
          'simpleads_ref_field'   => NULL,
        ]
      ],
      'simpleads_reference' => [
        'variables' => [
          'entity_type'      => NULL,
          'field_name'       => NULL,
          'entity_id'        => NULL,
          'rotation_type'    => NULL,
          'rotation_options' => NULL,
          'impressions'      => NULL,
          'show_in_modal'    => NULL,
          'modal_options'    => NULL,
        ]
      ],
      'simpleads_stats' => [
        'variables' => []
      ],
    ];
    foreach (array_keys($this->hook_simpleads_advertisement_types()) as $key) {
      $theme['simpleads_' . $key] = [
        'variables' => [
          'options' => NULL,
          'entity'  => NULL,
        ],
      ];
    }
    foreach ($this->hook_simpleads_graph_reports() as $key => $info) {
      $theme['simpleads_stats_' . (!empty($info['template']) ? $info['template'] : $key)] = [
        'variables' => [],
      ];
    }
    return $theme;
  }

  /**
   * Advertisement types.
   */
  public function hook_simpleads_advertisement_types() {
    $types = [
      'image'            => $this->t('Image'),
      'responsive_image' => $this->t('Responsive'),
      'html5'            => $this->t('HTML5'),
    ];
    $this->moduleHandler->alter('simpleads_advertisement_types', $types);
    return $types;
  }

  /**
   * Campaign types.
   */
  public function hook_simpleads_campaign_types() {
    $types = [
      'click'      => $this->t('Click'),
      'impression' => $this->t('Impression'),
      'date'       => $this->t('Date'),
    ];
    $this->moduleHandler->alter('simpleads_campaign_types', $types);
    return $types;
  }

  /**
   * Implements hook_page_attachments().
   */
  public function hook_page_attachments(&$page) {
    $config = \Drupal::config(BaseSettingsForm::CONFIG_NAME);
    $current_node_id = NULL;
    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node instanceof NodeInterface) {
      $current_node_id = $node->id();
    }
    $page['#attached']['drupalSettings']['simpleads']['current_node_id'] = $current_node_id;
  }

  /**
   * UI field mapping.
   * This function will hide/show fields based on the type selection in the UI.
   */
  public function hook_simpleads_ui_field_mapping() {
    $mapping = [
      'advertisement_form' => [
        'image'            => '.field--name-image',
        'responsive_image' => '.field--name-responsive-image-desktop, .field--name-responsive-image-tablet, .field--name-responsive-image-mobile',
        'html5'            => '.field--name-html5',
      ],
      'campaign_form' => [
        'click'      => '.field--name-click',
        'impression' => '.field--name-impression',
        'date'       => '.field--name-start-date, .field--name-end-date',
      ]
    ];
    $this->moduleHandler->alter('simpleads_ui_field_mapping', $mapping);
    return $mapping;
  }

  /**
   * Statistics types (graphs formatter).
   * This method will build tabs for SimpleAds statistics.
   */
  public function hook_simpleads_graph_reports() {
    $options = [
      'all_time' => [
        'label'    => $this->t('All Time'),
        'library'  => 'simpleads/simpleads.stats.js',
        'template' => 'all-time',
      ],
      '30day' => [
        'label'    => $this->t('Last 30 days'),
        'library'  => 'simpleads/simpleads.stats.js',
        'template' => 'last-month',
      ],
      '7days' => [
        'label'    => $this->t('Last Week'),
        'library'  => 'simpleads/simpleads.stats.js',
        'template' => 'last-week',
      ],
      'today' => [
        'label'    => $this->t('Today'),
        'library'  => 'simpleads/simpleads.stats.js',
        'template' => 'today',
      ],
      'table' => [
        'label'    => $this->t('Table'),
        'library'  => 'simpleads/simpleads.stats.js',
        'template' => 'table',
      ],
    ];
    $this->moduleHandler->alter('simpleads_graph_reports', $options);
    return $options;
  }

  /**
   * Implements template_preprocess_views_view().
   */
  public function hook_preprocess_views_view(&$variables) {
    $variables['#attached']['library'][] = 'simpleads/simpleads.views.js';
    $view = $variables['view'];
    $variables['view_id'] = $view->id();
    $variables['display_id'] = $view->current_display;
    $options = $view->style_plugin->options;
    $variables['rotation_type'] = $options['rotation'];
    $variables['multiple_random_limit'] = $options['multiple_random_limit'];
    $variables['impressions'] = (bool) $options['rotation_impressions'] ? 'true' : 'false';
    $variables['show_in_modal'] = $options['show_in_modal'];
    $variables['modal_options'] = json_encode([
      'delay_type'     => $options['modal_delay'],
      'page_visits'    => $options['modal_page_visits'],
      'visits_timeout' => $options['modal_visits_timeout'],
    ]);
    $variables['rotation_options'] = [
      // Slick slider options.
      'loop' => json_encode([
        'draggable'    => FALSE,
        'arrows'       => FALSE,
        'dots'         => FALSE,
        'fade'         => TRUE,
        'autoplay'     => TRUE,
        'pauseOnFocus' => FALSE,
        'pauseOnHover' => (bool) $options['rotation_pauseonhover'],
        'speed'        => (int) $options['rotation_speed'],
        'infinite'     => TRUE,
      ])
    ];
  }

}
