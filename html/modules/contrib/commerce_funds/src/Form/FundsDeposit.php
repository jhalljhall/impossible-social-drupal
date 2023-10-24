<?php

namespace Drupal\commerce_funds\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_funds\ProductManagerInterface;
use Drupal\commerce_funds\AvailableCurrenciesTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to deposit money on user account.
 */
class FundsDeposit extends FormBase {

  use AvailableCurrenciesTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * The product manager.
   *
   * @var \Drupal\commerce_funds\ProductManagerInterface
   */
  protected $productManager;

  /**
   * Class constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ProductManagerInterface $product_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->productManager = $product_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('commerce_funds.product_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_funds_deposit';
  }

  /**
   * {@inheritdoc}
   *
   * Https://www.drupal.org/docs/8/api/form-api/configformbase-with-simple-configuration-api.
   */
  protected function getEditableConfigNames() {
    return [
      'commerce_funds.deposit',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['amount'] = [
      '#type' => 'number',
      '#min' => 0.0,
      '#title' => $this->t('Deposit Amount'),
      '#description' => $this->t('Please enter the amount you wish to deposit.'),
      '#step' => 0.01,
      '#default_value' => 0,
      '#size' => 30,
      '#maxlength' => 128,
      '#required' => TRUE,
    ];

    $form['currency'] = $this->currencySelectForm($form, $form_state);

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Next'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $amount = $form_state->getValue('amount');

    // Error if amount equals 0.
    if ($amount == 0) {
      $form_state->setErrorByName('amount', $this->t('Amount must be a positive number.'));
      return;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $product_variation = $this->productManager->createProduct('deposit', $form_state->getValue('amount'), $form_state->getValue('currency'));
    /** @var \Drupal\commerce_product\Entity\ProductVariation $product_variation */
    $order = $this->productManager->createOrder($product_variation);

    // Redirect to checkout.
    $form_state->setRedirect('commerce_checkout.form', [
      'commerce_order' => $order->id(),
    ]);
  }

}
