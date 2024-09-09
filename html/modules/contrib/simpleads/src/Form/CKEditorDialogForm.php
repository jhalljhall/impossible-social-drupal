<?php

namespace Drupal\simpleads\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Xss;
use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\Ajax\EditorDialogSave;

/**
 * Ckeditor dialog form to insert SimpleAds block.
 */
class CKEditorDialogForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ckeditor5_simpleads_dialog_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $uuid = NULL) {
    $request = $this->getRequest();

    if ($uuid) {
      $form['uuid'] = [
        '#type' => 'value',
        '#value' => $uuid,
      ];
    }

    $form['group'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Advertisement group'),
      '#description'   => $this->t('Advertisement group to inject into content.'),
      '#options'       => $this->getAllGroups(),
      '#default_value' => (int) $request->get('group'),
      '#required'      => TRUE,
    ];
    $form['rotation'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Display'),
      '#description'   => $this->t('Advertisement display mode.'),
      '#options'       => [
        'loop'     => $this->t('Loop'),
        'multiple' => $this->t('Show multiple random'),
        'refresh'  => $this->t('Random on every page refresh'),
      ],
      '#default_value' => !empty($request->get('rotation')) ? $request->get('rotation') : 'loop',
    ];
    $form['rotation_impressions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Count impressions on each rotation once'),
      '#default_value' => !empty($request->get('rotation_impressions')),
      '#states' => [
        'visible' => [
          'select[name="rotation"]' => ['value' => 'loop']
        ]
      ],
    ];
    $form['multiple_random_limit'] = [
      '#type' => 'select',
      '#title' => $this->t('Limit number of ads to show'),
      '#description'   => $this->t('Controls the number of ads to show in a block.'),
      '#options' => array_combine(range(1, 25), range(1, 25)),
      '#default_value' => !empty($request->get('multiple_random_limit')) ? $request->get('multiple_random_limit') : 3,
      '#states' => [
        'visible' => [
          'select[name="rotation"]' => ['value' => 'multiple'],
        ]
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => !empty($request->get('rotation')) ? $this->t('Update') : $this->t('Insert'),
        '#button_type' => 'primary',
        '#ajax' => [
          'callback' => [$this, 'ajaxSubmitForm'],
        ],
      ],
    ];

    return $form;
  }

  /**
   * Ajax submit callback to insert or replace the html in ckeditor.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|array
   *   Ajax response for injecting html in ckeditor.
   */
  public static function ajaxSubmitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getErrors()) {
      return $form['group'];
    }
    else {

      $response = new AjaxResponse();

      $response->addCommand(new EditorDialogSave([
        'attributes' => [
          'data-group' => $form_state->getValue('group'),
          'data-rotation-type' => $form_state->getValue('rotation'),
          'data-random-limit' => $form_state->getValue('multiple_random_limit'),
          'data-impressions' => $form_state->getValue('rotation_impressions'),
          'data-rotation-options' => Json::encode([
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
      ]));

      $response->addCommand(new CloseModalDialogCommand());
      return $response;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $group = $form_state->getValue('group');
    if (empty($group)) {
      $form_state->setErrorByName('group', $this->t('Group field is required'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Required but not used.
  }

  /**
   * Get all groups.
   */
  protected function getAllGroups() {
    $groups_data = [];
    $groups = \Drupal::entityTypeManager()
      ->getStorage('simpleads_group')
      ->loadByProperties(['status' => TRUE]);
    foreach ($groups as $group) {
      $groups_data[$group->id()] = Html::decodeEntities($group->getName());
    }
    return $groups_data;
  }

}
