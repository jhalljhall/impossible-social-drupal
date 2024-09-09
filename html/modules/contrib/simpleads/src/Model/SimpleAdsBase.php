<?php

namespace Drupal\simpleads\Model;

use Drupal\file\Entity\File;
use Drupal\simpleads\Form\BaseSettingsForm;

class SimpleAdsBase extends Base {

  public function getEntityId() {
    if ($entity = $this->getEntity()) {
      return $entity->id();
    }
  }

  public function getGroupId() {
    return $this->getEntity()->getGroup();
  }

  public function getUrl() {
    return $this->getEntity()->getUrl();
  }

  public function getUrlTarget() {
    if ($this->getEntity()->isUrlOpenWindow()) {
      return '_blank';
    }
    return '_self';
  }

  public function getRenderedHtml() {
    $view_mode = \Drupal::config(BaseSettingsForm::CONFIG_NAME)->get('ads_view_mode');
    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('simpleads');
    $array = $view_builder->view($this->getEntity(), $view_mode);
    return \Drupal::service('renderer')->renderRoot($array);
  }

  public function model() {
    return [
      'entity_id'  => $this->getEntityId(),
      'group_id'   => $this->getGroupId(),
      'html'       => $this->getRenderedHtml(),
      'url'        => $this->getUrl(),
      'url_target' => $this->getUrlTarget(),
    ];
  }

}
