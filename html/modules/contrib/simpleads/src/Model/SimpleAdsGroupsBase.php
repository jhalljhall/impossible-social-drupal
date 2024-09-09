<?php

namespace Drupal\simpleads\Model;

class SimpleAdsGroupsBase extends Base {

  protected $entity_id;
  protected $ads;
  protected $node_ref_field;
  protected $simpleads_ref_field;
  protected $current_node_id;

  public function setEntityId($id) {
    $this->entity_id = $id;
    return $this;
  }

  public function getEntityId() {
    return (int) $this->entity_id;
  }

  public function setCurrentNode($node_id) {
    $this->current_node_id = $node_id;
    return $this;
  }

  public function getCurrentNode() {
    return $this->current_node_id;
  }

  public function setNodeRef($field_name) {
    $this->node_ref_field = $field_name;
    return $this;
  }

  public function getNodeRef() {
    return $this->node_ref_field;
  }

  public function setSimpleAdsRef($field_name) {
    $this->simpleads_ref_field = $field_name;
    return $this;
  }

  public function getSimpleAdsRef() {
    return $this->simpleads_ref_field;
  }

  public function setAds($ads) {
    $this->ads = $ads;
    return $this;
  }

  public function getAds() {
    return $this->ads;
  }

  public function model() {
    return [
      'items' => $this->getAds(),
      'count' => count($this->getAds())
    ];
  }

}
