<?php

namespace Drupal\commerce_webform_order\Plugin\WebformHandler;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Messenger\MessengerTrait;

/**
 * Provides helper methods to display/log debug messages.
 */
trait CommerceWebformOrderDebugTrait {

  use MessengerTrait;

  /**
   * The created cart order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $cart;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Adds a debug message to the webform process.
   */
  public function debug() {
    $t_args = [
      '%order_id' => $this->cart->get('order_id')->getString(),
      '%owner_email' => $this->cart->getEmail(),
    ];
    if ($this->cart->getEmail() !== NULL) {
      $this->messenger()->addWarning($this->t("Order #%order_id created to '%owner_email'.", $t_args), TRUE);
    }
    else {
      $this->messenger()->addWarning($this->t("Order #%order_id created.", $t_args), TRUE);
    }
    $debug_message = $this->buildDebugMessage($this->cart);
    $this->messenger()->addWarning($this->renderer->renderPlain($debug_message), TRUE);
  }

  /**
   * Build debug message.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   A commerce order.
   *
   * @return array
   *   Debug message.
   */
  protected function buildDebugMessage(OrderInterface $order) {
    // Title.
    $build = [
      '#type' => 'details',
      '#title' => $this->t('Debug: Order: @title', ['@title' => $this->label()]),
    ];

    // Values.
    $values = [
      $order->getStore()->getName() => $this->t('Store'),
      $order->get('order_id')->getString() => $this->t('Order ID'),
      'hr1' => '---',
    ];
    if ($order->getEmail() !== NULL) {
      $values += [$order->getEmail() => $this->t("Owner's e-mail")];
    }
    $values += [
      $order->getTotalPrice()->getNumber() => $this->t('Amount'),
      $order->getTotalPrice()->getCurrencyCode() => $this->t('Currency'),
      'hr2' => '---',
    ];

    foreach ($order->getItems() as $key => $order_item) {
      $values[$order_item->getTitle()] = $this->t('Item #@number', ['@number' => $key + 1]);
    }
    foreach ($values as $name => $title) {
      if ($title == '---') {
        $build[$name] = ['#markup' => '<hr />'];
      }
      else {
        $build[$name] = [
          '#type' => 'item',
          '#title' => $title,
          '#markup' => $name,
          '#wrapper_attributes' => [
            'class' => ['container-inline'],
            'style' => 'margin: 0;',
          ],
        ];
      }
    }

    return $build;
  }

}
