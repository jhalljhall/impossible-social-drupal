<?php

namespace Drupal\simpleads\Entity\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Component\Serialization\Json;

/**
 * Form controller for Campaign edit forms.
 *
 * @ingroup simpleads
 */
class CampaignForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\simpleads\Entity\Campaign */
    $form = parent::buildForm($form, $form_state);
    $form['#attached']['library'][] = 'simpleads/campaign.form.js';
    $form['#attached']['drupalSettings']['simpleads'] = simpleads_ui_field_mapping();
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#url' => Url::fromRoute('simpleads.campaign'),
      '#title' => $this->t('Cancel'),
      '#attributes' => [
        'class' => ['button', 'dialog-cancel'],
      ],
      '#weight' => 5,
    ];
    if (!empty($form['actions']['delete'])) {
      $ajax_attributes = [
        'class' => ['use-ajax'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode(['width' => 700]),
      ];
      $classes = !empty($form['actions']['delete']['#attributes']['class']) ? $form['actions']['delete']['#attributes']['class'] : [];
      $ajax_attributes['class'] = array_merge($ajax_attributes['class'], $classes);
      $form['actions']['delete']['#attributes'] = $ajax_attributes;
    }
    $form['actions']['#weight'] = 999;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;

    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        $message = $this->t('Created %label campaign.', [
          '%label' => $entity->label(),
        ]);
        \Drupal::logger('simpleads')->notice($message);
        \Drupal::messenger()->addMessage($message);
        break;

      default:
        $message = $this->t('Updated %label campaign.', [
          '%label' => $entity->label(),
        ]);
        \Drupal::logger('simpleads')->notice($message);
        \Drupal::messenger()->addMessage($message);
    }
    $form_state->setRedirect('entity.simpleads_campaign.canonical', ['simpleads_campaign' => $entity->id()]);
  }

}
