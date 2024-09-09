<?php

namespace Drupal\simpleads\Model;

class Base {

  protected $entity;

  public function setEntity($entity) {
    $this->entity = $entity;
    return $this;
  }

  public function userAccess($permission) {
    $user = \Drupal::currentUser();
    return $user->hasPermission($permission);
  }

  public function getEntity() {
    return $this->entity;
  }

  public function isBotDetected() {
  	return (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/bot|crawl|slurp|spider|mediapartners/i', $_SERVER['HTTP_USER_AGENT']));
  }

}
