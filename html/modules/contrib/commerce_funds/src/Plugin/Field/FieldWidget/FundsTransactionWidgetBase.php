<?php

namespace Drupal\commerce_funds\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\commerce_funds\FeesManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for 'Transaction widgets' plugin implementations.
 *
 * @ingroup field_widget
 */
abstract class FundsTransactionWidgetBase extends WidgetBase {

  /**
   * The fees manager.
   *
   * @var \Drupal\commerce_funds\FeesManagerInterface
   */
  protected $feesManager;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, FeesManagerInterface $fees_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);

    $this->feesManager = $fees_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('commerce_funds.fees_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // Add a custom entity builder handler.
    $form['#entity_builders']['update_funds_transaction'] = 'Drupal\commerce_funds\Element\FundsTransaction::updateFundsTransaction';
    // A transaction exist? We render it.
    if ($items->first()->getString()) {
      $renderable_array = $items->view('full');
      array_unshift($renderable_array, [
        '#markup' => '<div class="transaction-not-editable"><i>' . t('Transaction fields are not editable.') . '</i></div>',
      ]);

      return $renderable_array;
    }

    $settings = $this->getFieldSettings();

    $element += [
      '#type' => 'details',
      '#open' => TRUE,
    ];

    $element['target_id'] = [
      '#type' => 'funds_transaction',
      '#transaction_type' => '',
      '#available_currencies' => $settings['available_currencies'],
      '#notes_enabled' => $settings['enable_notes'],
    ];

    // Default widget ?
    if ($this->isDefaultValueWidget($form_state)) {
      // Make sure no properties are required on the default value widget.
      $element['target_id']['#after_build'][] = [
        get_class($this),
        'makeFieldsOptional',
      ];
      // Add a wrapper for the ajax callback.
      // We currently need to force a hardcoded ID here.
      // @see https://www.drupal.org/project/drupal/issues/2821793.
      // @see Drupal\commerce_funds\Plugin\Field\FieldType\FundsTransactionItem.
      $element['#prefix'] = '<div id="funds-transaction-wrapper">';
      $element['#suffix'] = '</div>';
    }

    return $element;
  }

  /**
   * Form API callback: Makes all funds field properties optional.
   */
  public static function makeFieldsOptional(array $element, FormStateInterface $form_state) {
    foreach (Element::getVisibleChildren($element) as $key) {
      if (!empty($element[$key]['#required'])) {
        $element[$key]['#required'] = FALSE;
      }
    }

    return $element;
  }

}
