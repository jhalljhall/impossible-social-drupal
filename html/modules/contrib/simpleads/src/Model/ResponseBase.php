<?php

namespace Drupal\simpleads\Model;

class ResponseBase {

  protected $data;
  protected $code;

  private $messages = [
    'success'         => 'Success',
    'invalid_group'   => 'Invalid Entity ID supplied',
    'invalid_entity'  => 'Invalid Entity ID supplied',
    'group_not_found' => 'Entity ID not found',
    'no_ads_found'    => 'No ads found for supplied entity',
  ];

  public function setData($data) {
    $this->data = $data;
    return $this;
  }

  public function getData() {
    return $this->data;
  }

  public function setCode($code) {
    $this->code = $code;
    return $this;
  }

  public function getCode() {
    return $this->code;
  }

  public function getMessage() {
    return !empty($this->messages[$this->getCode()])
      ? $this->messages[$this->getCode()]
      : 'Unknown error occured';
  }

  public function model() {
    return [
      'code'    => $this->getCode(),
      'message' => $this->getMessage(),
      'data'    => $this->getData(),
    ];
  }

}
