<?php

namespace Drupal\commerce_webform_order\Plugin\Commerce\CheckoutPane;

use Drupal\commerce\Response\NeedsRedirectException;
use Drupal\commerce_payment\Exception\DeclineException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\CheckoutPane\PaymentProcess as PaymentProcessBase;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\ManualPaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsStoredPaymentMethodsInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides the payment process pane.
 *
 * @CommerceCheckoutPane(
 *   id = "commerce_webform_order_payment_process",
 *   label = @Translation("Payment process (Commerce Webform Order)"),
 *   wrapper_element = "container",
 * )
 */
class PaymentProcess extends PaymentProcessBase {

  /**
   * {@inheritdoc}
   */
  public function isVisible() {
    if ($this->order->isPaid() || $this->order->getTotalPrice()->isZero()) {
      // No payment is needed if the order is free or has already been paid.
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    // The payment gateway is currently always required to be set.
    if ($this->order->get('payment_gateway')->isEmpty()) {
      $this->messenger()->addError($this->t('No payment gateway selected.'));
      $this->redirectToCancel();
    }

    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
    $payment_gateway = $this->order->payment_gateway->entity;
    $payment_gateway_plugin = $payment_gateway->getPlugin();

    $payment = $this->createPayment($payment_gateway);
    $next_step_id = $this->checkoutFlow->getNextStepId($this->getStepId());

    if ($payment_gateway_plugin instanceof SupportsStoredPaymentMethodsInterface && !$this->order->get('payment_method')->isEmpty()) {
      try {
        $payment->payment_method = $this->order->get('payment_method')->entity;
        $payment_gateway_plugin->createPayment($payment, $this->configuration['capture']);
        $this->checkoutFlow->redirectToStep($next_step_id);
      }
      catch (DeclineException $e) {
        $message = $this->t('We encountered an error processing your payment method. Please verify your details and try again.');
        $this->messenger()->addError($message);
        $this->redirectToCancel();
      }
      catch (PaymentGatewayException $e) {
        $this->logger->error($e->getMessage());
        $message = $this->t('We encountered an unexpected error processing your payment method. Please try again later.');
        $this->messenger()->addError($message);
        $this->redirectToCancel();
      }
    }
    elseif ($payment_gateway_plugin instanceof OffsitePaymentGatewayInterface) {
      $complete_form['actions']['next']['#value'] = $this->t('Proceed to @gateway', [
        '@gateway' => $payment_gateway_plugin->getDisplayLabel(),
      ]);
      // Make sure that the payment gateway's onCancel() method is invoked,
      // by pointing the "Go back" link to the cancel URL.
      $complete_form['actions']['next']['#suffix'] = Link::fromTextAndUrl($this->t('Go back'), $this->buildCancelUrl())->toString();
      // Actions are not needed by gateways that embed iframes or redirect
      // via GET. The inline form can show them when needed (redirect via POST).
      $complete_form['actions']['#access'] = FALSE;

      $inline_form = $this->inlineFormManager->createInstance('payment_gateway_form', [
        'operation' => 'offsite-payment',
        'catch_build_exceptions' => FALSE,
      ], $payment);

      $pane_form['offsite_payment'] = [
        '#parents' => array_merge($pane_form['#parents'], ['offsite_payment']),
        '#inline_form' => $inline_form,
        '#return_url' => $this->buildReturnUrl()->toString(),
        '#cancel_url' => $this->buildCancelUrl()->toString(),
        '#capture' => $this->configuration['capture'],
      ];
      try {
        $pane_form['offsite_payment'] = $inline_form->buildInlineForm($pane_form['offsite_payment'], $form_state);
      }
      catch (PaymentGatewayException $e) {
        $this->logger->error($e->getMessage());
        $message = $this->t('We encountered an unexpected error processing your payment. Please try again later.');
        $this->messenger()->addError($message);
        $this->redirectToCancel();
      }

      return $pane_form;
    }
    elseif ($payment_gateway_plugin instanceof ManualPaymentGatewayInterface) {
      try {
        $payment_gateway_plugin->createPayment($payment);
        $this->checkoutFlow->redirectToStep($next_step_id);
      }
      catch (PaymentGatewayException $e) {
        $this->logger->error($e->getMessage());
        $message = $this->t('We encountered an unexpected error processing your payment. Please try again later.');
        $this->messenger()->addError($message);
        $this->redirectToCancel();
      }
    }
    else {
      $this->logger->error('Unable process payment with :plugin_id', [
        ':plugin_id' => $payment_gateway_plugin->getPluginId(),
      ]);
      $message = $this->t('We encountered an unexpected error processing your payment. Please try again later.');
      $this->messenger()->addError($message);
      $this->redirectToCancel();
    }
  }

  /**
   * Builds the URL to the "cancel" page.
   *
   * @return \Drupal\Core\Url
   *   The "cancel" page URL.
   */
  protected function buildCancelUrl() {
    $cancel_url = $this->order->getData('commerce_webform_order_cancel_url');

    // If a cancel URL has not been provided, we use the front page.
    if (empty($cancel_url)) {
      return Url::fromRoute(
        '<front>',
        [],
        ['absolute' => TRUE]
      );
    }
    else {
      // If path is external transform it into relative URL and then return
      // the absolute URL.
      if (UrlHelper::isExternal($cancel_url)) {
        $parsed_url = parse_url($cancel_url) + [
          'path' => '',
          'query' => '',
          'fragment' => '',
        ];

        $cancel_url = $parsed_url['path'];
        if (!empty($parsed_url['query']) || !empty($parsed_url['fragment'])) {
          $cancel_url .= '?' . $parsed_url['query'] . $parsed_url['fragment'];
        }
      }

      return Url::fromUserInput(
        $cancel_url,
        ['absolute' => TRUE]
      );
    }
  }

  /**
   * Redirects an order to the cancel page.
   *
   * @throws \Drupal\commerce\Response\NeedsRedirectException
   */
  protected function redirectToCancel() {
    // Reset the checkout step, so users can restart a new flow.
    $this->order->set('checkout_step', NULL);
    // Ensure the order is not locked.
    $this->order->unlock();
    $this->order->save();

    throw new NeedsRedirectException($this->buildCancelUrl()->toString());
  }

}
