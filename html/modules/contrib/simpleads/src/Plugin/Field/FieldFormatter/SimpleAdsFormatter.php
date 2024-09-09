<?php

namespace Drupal\simpleads\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\simpleads\Form\BaseSettingsForm;

/**
 * Plugin implementation of the 'simpleads_advertisement' formatter.
 *
 * @FieldFormatter(
 *   id = "simpleads_advertisement",
 *   label = @Translation("Default"),
 *   field_types = {
 *     "simpleads_advertisement"
 *   }
 * )
 */
class SimpleAdsFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    foreach ($items as $delta => $item) {
      $entity = $item->value;
      $elements[$delta] = [
        '#theme'    => 'simpleads_' . $entity->getType(),
        '#entity'   => $entity,
        '#options'  => $this->getAdvertisementOptions($entity),
        '#cache'    => [
          'tags'    => ['simpleads_group'],
          'context' => ['url.query_args'],
        ],
      ];
    }
    return $elements;
  }

  /**
   * Generate data for this advertisement.
   */
  protected function getAdvertisementOptions($entity) {
    $config = \Drupal::config(BaseSettingsForm::CONFIG_NAME);
    return [
      'type'       => $entity->getType(),
      'desktop'    => [
        'src'   => ($file = $entity->getResponsiveImageDesktop()) ? $this->generateAbsoluteUrl($file['file']->getFileUri()) : '',
        'prop'  => ($file = $entity->getResponsiveImageDesktop()) ? $file['prop'] : [],
        'query' => $config->get('desktop_media_query'),
      ],
      'tablet'     => [
        'src'   => ($file = $entity->getResponsiveImageTablet()) ? $this->generateAbsoluteUrl($file['file']->getFileUri()) : '',
        'prop'  => ($file = $entity->getResponsiveImageTablet()) ? $file['prop'] : [],
        'query' => $config->get('tablet_media_query'),
      ],
      'mobile'     => [
        'src'   => ($file = $entity->getResponsiveImageMobile()) ? $this->generateAbsoluteUrl($file['file']->getFileUri()) : '',
        'prop'  => ($file = $entity->getResponsiveImageMobile()) ? $file['prop'] : [],
        'query' => $config->get('mobile_media_query'),
      ],
      'image'      => ($file = $entity->getImage()) ? $this->generateAbsoluteUrl($file['file']->getFileUri()) : '',
      'image_prop' => ($file = $entity->getImage()) ? $file['prop'] : '',
      'html5'      => $entity->getHtml5IndexPath(),
    ];
  }

  protected function generateAbsoluteUrl($uri) {
    return \Drupal::service('file_url_generator')->generateAbsoluteString($uri);
  }

}
