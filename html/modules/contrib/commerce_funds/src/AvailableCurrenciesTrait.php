<?php

namespace Drupal\commerce_funds;

use Drupal\Core\Form\FormStateInterface;

/**
 * Allows field types to limit the available currencies.
 */
trait AvailableCurrenciesTrait {

  /**
   * A list of available currencies.
   *
   * @var array
   */
  protected static $availableCurrencies = [];

  /**
   * Defines the default field-level settings.
   *
   * @return array
   *   A list of default settings, keyed by the setting name.
   */
  public static function defaultCurrencySettings() {
    return [
      'available_currencies' => [],
    ];
  }

  /**
   * Builds the field settings form.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the (entire) configuration form.
   *
   * @return array
   *   The element.
   */
  public function currencySettingsForm(array $form, FormStateInterface $form_state) {
    $currencies = \Drupal::entityTypeManager()->getStorage('commerce_currency')->loadMultiple();
    $currency_codes = [];
    /** @var \Drupal\commerce_price\Entity\Currency $currency */
    foreach ($currencies as $currency) {
      $currency_codes[$currency->getCurrencyCode()] = $currency->getCurrencyCode();
    }

    $element['available_currencies'] = [
      '#type' => 'select',
      '#title' => $this->t('Available currencies'),
      '#description' => $this->t('If no currencies are selected, all currencies will be available.'),
      '#options' => $currency_codes,
      '#default_value' => $this->getSetting('available_currencies'),
      '#multiple' => TRUE,
      '#size' => 5,
    ];

    return $element;
  }

  /**
   * Builds select element form.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the (entire) configuration form.
   *
   * @return array
   *   The currency select element.
   */
  public function currencySelectForm(array $form, FormStateInterface $form_state) {
    $currencies = \Drupal::entityTypeManager()->getStorage('commerce_currency')->loadMultiple();
    $currency_codes = [];
    /** @var \Drupal\commerce_price\Entity\Currency $currency */
    foreach ($currencies as $currency) {
      $currency_codes[$currency->getCurrencyCode()] = $currency->getCurrencyCode();
    }
    // Make sure currencies are sorted.
    ksort($currencies);

    return [
      '#type' => 'select',
      '#title' => $this->t('Select Currency'),
      '#description' => $this->t('Select a currency.'),
      '#options' => $currency_codes,
    ];
  }

}
