<?php

namespace Drupal\commerce_webform_order;

use Drupal\commerce_payment\PaymentOption;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsStoredPaymentMethodsInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Builds payment options.
 */
class PaymentOptionsBuilder implements PaymentOptionsBuilderInterface {

  use StringTranslationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new PaymentOptionsBuilder object.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation.
   */
  public function __construct(AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, TranslationInterface $string_translation) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptions(array $payment_gateways = []) {
    if (empty($payment_gateways)) {
      /** @var \Drupal\commerce_payment\PaymentGatewayStorageInterface $payment_gateway_storage */
      $payment_gateway_storage = $this->entityTypeManager->getStorage('commerce_payment_gateway');
      $payment_gateways = $payment_gateway_storage->loadByProperties(
        ['status', TRUE]
      );
    }

    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface[] $payment_gateways_with_payment_methods */
    $payment_gateways_with_payment_methods = array_filter($payment_gateways, function ($payment_gateway) {
      /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
      return $payment_gateway->getPlugin() instanceof SupportsStoredPaymentMethodsInterface;
    });

    $options = [];
    // 1) Add options to reuse stored payment methods for known customers.
    if ($this->currentUser->isAuthenticated()) {
      /** @var \Drupal\user\UserInterface $account */
      $account = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());

      /** @var \Drupal\commerce_payment\PaymentMethodStorageInterface $payment_method_storage */
      $payment_method_storage = $this->entityTypeManager->getStorage('commerce_payment_method');

      foreach ($payment_gateways_with_payment_methods as $payment_gateway) {
        $payment_methods = $payment_method_storage->loadReusable($account, $payment_gateway);

        foreach ($payment_methods as $payment_method_id => $payment_method) {
          $options[$payment_method_id] = new PaymentOption([
            'id' => $payment_method_id,
            'label' => $payment_method->label(),
            'payment_gateway_id' => $payment_gateway->id(),
            'payment_method_id' => $payment_method_id,
            'payment_method_type_id' => $payment_method->bundle(),
          ]);
        }
      }
    }

    $payment_method_type_counts = [];
    // Count how many new payment method options will be built per gateway
    // with payment methods.
    foreach ($payment_gateways_with_payment_methods as $payment_gateway) {
      $payment_method_types = $payment_gateway->getPlugin()->getPaymentMethodTypes();

      foreach ($payment_method_types as $payment_method_type_id => $payment_method_type) {
        if (!isset($payment_method_type_counts[$payment_method_type_id])) {
          $payment_method_type_counts[$payment_method_type_id] = 1;
        }
        else {
          $payment_method_type_counts[$payment_method_type_id]++;
        }
      }
    }

    foreach ($payment_gateways as $payment_gateway_id => $payment_gateway) {
      // 2) Add options to create new stored payment methods of supported types.
      if (isset($payment_gateways_with_payment_methods[$payment_gateway_id])) {
        $payment_gateway_plugin = $payment_gateway->getPlugin();
        $payment_method_types = $payment_gateway_plugin->getPaymentMethodTypes();

        foreach ($payment_method_types as $payment_method_type_id => $payment_method_type) {
          $option_id = 'new--' . $payment_method_type_id . '--' . $payment_gateway->id();
          $option_label = $payment_method_type->getCreateLabel();
          // If there is more than one option for this payment method type,
          // append the payment gateway label to avoid duplicate option labels.
          if ($payment_method_type_counts[$payment_method_type_id] > 1) {
            $option_label = $this->t('@payment_method_label (@payment_gateway_label)', [
              '@payment_method_label' => $payment_method_type->getCreateLabel(),
              '@payment_gateway_label' => $payment_gateway_plugin->getDisplayLabel(),
            ]);
          }

          $options[$option_id] = new PaymentOption([
            'id' => $option_id,
            'label' => $option_label,
            'payment_gateway_id' => $payment_gateway->id(),
            'payment_method_type_id' => $payment_method_type_id,
          ]);
        }
      }

      // 3) Add options for the remaining gateways.
      else {
        $options[$payment_gateway_id] = new PaymentOption([
          'id' => $payment_gateway_id,
          'label' => $payment_gateway->getPlugin()->getDisplayLabel(),
          'payment_gateway_id' => $payment_gateway_id,
        ]);
      }
    }

    return $options;
  }

}
