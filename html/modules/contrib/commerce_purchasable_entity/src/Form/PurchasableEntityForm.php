<?php

namespace Drupal\commerce_purchasable_entity\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the purchasable entity edit forms.
 */
class PurchasableEntityForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);

    $entity = $this->getEntity();

    $message_arguments = ['%label' => $entity->toLink()->toString()];
    $logger_arguments = [
      '%label' => $entity->label(),
      'link' => $entity->toLink($this->t('View'))->toString(),
    ];

    switch ($result) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('New purchasable entity %label has been created.', $message_arguments));
        $this->logger('commerce_purchasable_entity')->notice('Created new purchasable entity %label', $logger_arguments);
        break;

      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t('The purchasable entity %label has been updated.', $message_arguments));
        $this->logger('commerce_purchasable_entity')->notice('Updated purchasable entity %label.', $logger_arguments);
        break;
    }

    $form_state->setRedirect('entity.commerce_purchasable_entity.collection');

    return $result;
  }

}
