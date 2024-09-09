<?php

namespace Drupal\simpleads\Plugin\Filter;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a text filter that turns <simpleads> tags into markup.
 *
 * @Filter(
 *   id = "simpleads",
 *   title = @Translation("SimpleAds"),
 *   description = @Translation("Converts &#60;simpleads&#62; to an advertisement block."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
 *   weight = 100,
 * )
 *
 * @internal
 */
class SimpleAdsFilter extends FilterBase implements ContainerFactoryPluginInterface, TrustedCallbackInterface {

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Renderer $renderer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('renderer'));
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult($text);
    if (stristr($text, '<simpleads') === FALSE) {
      return $result;
    }

    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);
    foreach ($xpath->query('//simpleads') as $element) {
      $group = (int) $element->getAttribute('data-group');
      $rotation = $element->getAttribute('data-rotation-type');
      $multiple_random_limit = $element->getAttribute('data-random-limit');
      $rotation_impressions = $element->getAttribute('data-impressions');

      // Delete the consumed attributes.
      $element->removeAttribute('data-group');
      $element->removeAttribute('data-rotation-type');
      $element->removeAttribute('data-random-limit');
      $element->removeAttribute('data-impressions');

      $build = [];
      $build['#attached']['library'][] = 'simpleads/simpleads.block.js';
      $build['simpleads'] = [
        '#theme'                 => 'simpleads_advertisement',
        '#prefix'                => '<div class="block-simpleads">',
        '#suffix'                => '</div>',
        '#group'                 => $group,
        '#node_ref_field'        => 0,
        '#simpleads_ref_field'   => 0,
        '#rotation_type'         => $rotation,
        '#multiple_random_limit' => $multiple_random_limit,
        '#impressions'           => (bool) $rotation_impressions ? 'true' : 'false',
        '#show_in_modal'         => FALSE,
        '#modal_options'         => json_encode([
          'delay_type'     => 0,
          'page_visits'    => 0,
          'visits_timeout' => 0,
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
            'pauseOnHover' => TRUE,
            'speed'        => 1000,
            'infinite'     => TRUE,
          ]),
        ],
        '#cache' => [
          'tags'    => ['simpleads_group_' . $group, 'simpleads_group'],
          'context' => ['url.query_args'],
        ],
      ];
      $this->renderIntoDomNode($build, $element, $result);
    }
    $result->setProcessedText(Html::serialize($dom));
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return [];
  }

  /**
   * Renders the given render array into the given DOM node.
   */
  protected function renderIntoDomNode(array $build, \DOMNode $node, FilterProcessResult &$result) {
    $markup = $this->renderer->executeInRenderContext(new RenderContext(), function () use (&$build) {
      return $this->renderer->render($build);
    });
    $result = $result->merge(BubbleableMetadata::createFromRenderArray($build));
    static::replaceNodeContent($node, $markup);
  }

  /**
   * Replaces the contents of a DOMNode.
   */
  protected static function replaceNodeContent(\DOMNode &$node, $content) {
    if (strlen($content)) {
      $replacement_nodes = Html::load($content)->getElementsByTagName('body')
        ->item(0)
        ->childNodes;
    }
    else {
      $replacement_nodes = [$node->ownerDocument->createTextNode('')];
    }

    foreach ($replacement_nodes as $replacement_node) {
      $replacement_node = $node->ownerDocument->importNode($replacement_node, TRUE);
      $node->parentNode->insertBefore($replacement_node, $node);
    }
    $node->parentNode->removeChild($node);
  }

}
