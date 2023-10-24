<?php

namespace Drupal\commerce_funds\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to configure the exchange rates.
 */
class ConfigureExchangeRates extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Class constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger, ModuleHandlerInterface $module_handler) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('messenger'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_funds_configure_exchange_rates';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'commerce_funds.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('commerce_funds.settings');

    $store = $this->entityTypeManager->getStorage('commerce_store')->loadDefault();
    // If no store set a message.
    if (!$store) {
      $message = $this->t('You haven\'t configured a store yet. Please <a href="@url">configure one</a> before.', [
        '@url' => Url::fromRoute('entity.commerce_store.collection')->toString(),
      ]);
      $this->messenger->addError($message);
      return $form;
    }

    $currencies = $this->entityTypeManager->getStorage('commerce_currency')->loadMultiple();
    // If just one currency set a message.
    if (count($currencies) == 1) {
      $message = $this->t('You just have one currency enabled on your store. <a href="@url">Add more</a> to provide currency conversion.', [
        '@url' => Url::fromRoute('entity.commerce_currency.collection')->toString(),
      ]);
      $this->messenger->addError($message);
      return $form;
    }

    // If commerce_exchanger is not installed.
    if (!$this->moduleHandler->moduleExists('commerce_exchanger')) {
      $message = $this->t('To use conversion feature you should install <a href="@url">Commerce exchanger</a> module.', [
        '@url' => 'https://www.drupal.org/project/commerce_exchanger',
      ]);
      $this->messenger->addError($message);
      return $form;
    }

    /** @var \Drupal\commerce_exchanger\Entity\ExchangeRatesInterface $providers */
    $providers = $this->entityTypeManager->getStorage('commerce_exchange_rates')->loadMultiple();

    $exchange_rates = [];
    foreach ($providers as $provider) {
      if ($provider->status()) {
        $exchange_rates[$provider->id()] = $provider->label();
      }
    }

    if (!$exchange_rates) {
      $message = $this->t('Add at least one <a href="@url">currency exchange rates</a> first.', [
        '@url' => Url::fromRoute('entity.commerce_exchange_rates.collection')->toString(),
      ]);
      $this->messenger->addError($message);
    }

    $form['exchange_rate_provider'] = [
      '#type' => 'select',
      '#title' => $this->t('Exchange rate API'),
      '#description' => $this->t('Select which external service you want to use for calculating exchange rates between currencies'),
      '#options' => $exchange_rates,
      '#empty_value' => '',
      '#default_value' => $config->get('exchange_rate_provider'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('commerce_funds.settings')
      ->set('exchange_rate_provider', $form_state->getValue('exchange_rate_provider'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
