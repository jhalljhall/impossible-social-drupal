<?php

namespace Drupal\simpleads\Plugin\Field\FieldFormatter;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'simpleads_stats_chart' formatter.
 *
 * @FieldFormatter(
 *   id = "simpleads_stats_chart",
 *   label = @Translation("Charts"),
 *   field_types = {
 *     "simpleads_stats"
 *   }
 * )
 */
class SimpleAdsStatsChartFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Construct a SimpleAdsStatsChartFormatter object
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, RendererInterface $renderer) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'clicks'             => 'rgb(27,163,156)',
      'clicks_unique'      => 'rgb(40,162,40)',
      'impressions'        => 'rgb(255,69,0)',
      'impressions_unique' => 'rgb(255,0,255)',
      'ctr'                => 'rgb(255,0,0)',
      'default_tab'        => 'all_time',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['clicks'] = [
      '#title'         => $this->t('Clicks color'),
      '#description'   => $this->t('Chart color (RGB only)'),
      '#type'          => 'textfield',
      '#default_value' => $this->getSetting('clicks'),
    ];
    $element['clicks_unique'] = [
      '#title'         => $this->t('Unique clicks color'),
      '#description'   => $this->t('Chart color (RGB only)'),
      '#type'          => 'textfield',
      '#default_value' => $this->getSetting('clicks_unique'),
    ];
    $element['impressions'] = [
      '#title'         => $this->t('Impressions color'),
      '#description'   => $this->t('Chart color (RGB only)'),
      '#type'          => 'textfield',
      '#default_value' => $this->getSetting('impressions'),
    ];
    $element['impressions_unique'] = [
      '#title'         => $this->t('Unique impressions color'),
      '#description'   => $this->t('Chart color (RGB only)'),
      '#type'          => 'textfield',
      '#default_value' => $this->getSetting('impressions_unique'),
    ];
    $element['ctr'] = [
      '#title'         => $this->t('CTR color'),
      '#description'   => $this->t('Chart color (RGB only)'),
      '#type'          => 'textfield',
      '#default_value' => $this->getSetting('ctr'),
    ];
    $tabs = [];
    foreach (simpleads_graph_reports() as $key => $val) {
      $tabs[$key] = $val['label'];
    }
    $element['default_tab'] = [
      '#title'         => $this->t('Default active tab'),
      '#description'   => $this->t('Indicates which tab to be active by default on the statistics page.'),
      '#type'          => 'select',
      '#options'       => $tabs,
      '#default_value' => $this->getSetting('default_tab'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $reports = simpleads_graph_reports();
    $tabs = [];
    $tabs_contents = [];
    foreach ($reports as $type => $info) {
      $elements['#attached']['library'][] = $info['library'];
      $tabs[$type] = $info['label'];
      $build[$type] = ['#theme' => 'simpleads_stats_' . $info['template']];
      $tabs_contents[$type] = $this->renderer->render($build[$type]);
    }
    $elements['#attached']['drupalSettings']['simpleads']['entity_id'] = $items->getEntity()->id();
    $elements['#attached']['drupalSettings']['simpleads']['chartColors'] = [
      'clicks'             => $this->getSetting('clicks'),
      'clicks_unique'      => $this->getSetting('clicks_unique'),
      'impressions'        => $this->getSetting('impressions'),
      'impressions_unique' => $this->getSetting('impressions_unique'),
      'ctr'                => $this->getSetting('ctr'),
    ];
    $elements['#attached']['drupalSettings']['simpleads']['statsDefaultTab'] = $this->getSetting('default_tab');
    $elements['#attached']['drupalSettings']['simpleads']['stats_tabs'] = $tabs;
    $elements['#attached']['drupalSettings']['simpleads']['stats_tabs_content'] = $tabs_contents;
    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#theme'  => 'simpleads_stats',
        '#cache' => [
          'tags'    => ['simpleads_group'],
          'context' => ['url.query_args'],
        ],
      ];
    }
    return $elements;
  }

}
