<?php

namespace Drupal\commerce_funds\EventSubscriber;

use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_funds\Entity\Transaction;
use Drupal\commerce_funds\TransactionManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class OrderPaidSubscriber.
 *
 * @package Drupal\commerce_funds
 */
class OrderUpdateSubscriber implements EventSubscriberInterface {

  /**
   * The transaction manager.
   *
   * @var \Drupal\commerce_funds\TransactionManagerInterface
   */
  protected $transactionManager;

  /**
   * Class constructor.
   */
  public function __construct(TransactionManagerInterface $transaction_manager) {
    $this->transactionManager = $transaction_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
    $container->get('commerce_funds.transaction_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[OrderEvents::ORDER_PAID] = ['updateAccountBalance', 100];
    return $events;
  }

  /**
   * Update account balance.
   *
   * This method is called whenever commerce_order.commerce_order.update is
   * dispatched.
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $event
   *   The order event.
   */
  public function updateAccountBalance(OrderEvent $event) {
    $order = $event->getOrder();
    if ($order->bundle() === 'deposit' && $order->isPaid()) {
      // Check if there is a transaction
      // attached to the order (populated at field level).
      $transactions = $order->get('field_transaction')->referencedEntities();
      if ($transaction = reset($transactions)) {
        // Make sure the transaction is not complete.
        if ($transaction->getStatus() != Transaction::TRANSACTION_STATUS['completed']) {
          $this->transactionManager->addDepositToBalance($order);
        }
      }
      // No transaction yet?
      // Order has been populated from regular form.
      else {
        $this->transactionManager->addDepositToBalance($order);
      }
    }
  }

}
