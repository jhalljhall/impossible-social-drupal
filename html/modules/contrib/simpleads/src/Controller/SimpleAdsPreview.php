<?php

namespace Drupal\simpleads\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\editor\Entity\Editor;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns the preview for SimpleAds.
 */
class SimpleAdsPreview extends ControllerBase {

  use StringTranslationTrait;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer')
    );
  }

  /**
   * The controller constructor.
   *
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer service.
   */
  public function __construct(Renderer $renderer) {
    $this->renderer = $renderer;
  }

  /**
   * Controller callback that renders the preview for CKeditor.
   */
  public function preview(Request $request, Editor $editor) {
    $group = $request->query->get('group');
    $rotation = $request->query->get('rotation');

    try {
      if (!$group || !$rotation) {
        throw new \Exception();
      }

      $group = (int) $group;
      $rotation = Xss::filter($rotation);
      $multiple_random_limit = Xss::filter($request->query->get('multiple_random_limit'));
      $rotation_impressions = Xss::filter($request->query->get('rotation_impressions'));

      
      $group = \Drupal::entityTypeManager()
        ->getStorage('simpleads_group')
        ->load($group);
      $group_label = '';
      if ($group) {
        $group_label = $group->label();
      }

      $types = [
        'loop'    => $this->t('Loop'),
        'multiple'    => $this->t('Show multiple random'),
        'refresh' => $this->t('Random on every page refresh'),
      ];

      $build = [
        '#type' => 'inline_template',
        '#template' => '<div class="preview-ad-label">{{ label }}</div>
          <div class="ad-preview-wrapper">
          {% if group %}
            <div class="preview-ad-item preview-ad-group"><span>{{ group }}</span></div>
          {% else %}
            <div class="preview-ad-item preview-ad-group-na">{{ group_na }}</div>
          {% endif %}
          {% if type_label %}
            <div class="preview-ad-item preview-ad-type"><span>{{ type_label }}</span></div>
            {% if type == "multiple" %}
              <div class="preview-ad-item preview-ad-limit"><span>{{ limit }}</span></div>
            {% endif %}
          {% else %}
            <div class="preview-ad-item preview-ad-type-na">{{ type_na }}</div>
          {% endif %}
          </div>',
        '#context' => [
          'label' => $this->t('SimpleAds'),
          'group_na' => $this->t('Invalid Ad Group'),
          'type_na' => $this->t('Invalid Ad Type'),
          'group' => $group_label,
          'type' => $rotation,
          'type_label' => !empty($types[$rotation]) ? $types[$rotation] : '',
          'limit' => $multiple_random_limit,
        ],
      ];
    }
    catch (\Exception $e) {
      $build = [
        'markup' => [
          '#type' => 'markup',
          '#markup' => $this->t('Incorrect configuration for SimpleAds.'),
        ],
      ];
    }
    $renderer = \Drupal::service('renderer');
    return new Response($renderer->renderRoot($build));
  }

  /**
   * Access callback for viewing the preview.
   *
   * @param \Drupal\editor\Entity\Editor $editor
   *   The editor.
   * @param \Drupal\Core\Session\AccountProxy $account
   *   The current user.
   *
   * @return \Drupal\Core\Access\AccessResult|\Drupal\Core\Access\AccessResultReasonInterface
   *   The acccess result.
   */
  public function checkAccess(Editor $editor, AccountProxy $account) {
    return AccessResult::allowedIfHasPermission($account, 'use text format ' . $editor->getFilterFormat()->id());
  }

}
