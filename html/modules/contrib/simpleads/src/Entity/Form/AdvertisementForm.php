<?php

namespace Drupal\simpleads\Entity\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form controller for Advertisement edit forms.
 *
 * @ingroup simpleads
 */
class AdvertisementForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\simpleads\Entity\Advertisement */
    $form = parent::buildForm($form, $form_state);
    $form['#attached']['library'][] = 'simpleads/advertisement.form.js';
    $form['#attached']['drupalSettings']['simpleads'] = simpleads_ui_field_mapping();
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#url' => Url::fromRoute('entity.simpleads.collection'),
      '#title' => $this->t('Cancel'),
      '#attributes' => [
        'class' => ['button', 'dialog-cancel'],
      ],
      '#weight' => 5,
    ];
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
        $message = $this->t('Created %label advertisement.', [
          '%label' => $entity->getTitle(),
        ]);
        \Drupal::logger('simpleads')->notice($message);
        \Drupal::messenger()->addMessage($message);
        break;

      default:
        $message = $this->t('Updated %label advertisement.', [
          '%label' => $entity->getTitle(),
        ]);
        \Drupal::logger('simpleads')->notice($message);
        \Drupal::messenger()->addMessage($message);
    }
    $form_state->setRedirect('entity.simpleads.canonical', ['simpleads' => $entity->id()]);
  }

}
