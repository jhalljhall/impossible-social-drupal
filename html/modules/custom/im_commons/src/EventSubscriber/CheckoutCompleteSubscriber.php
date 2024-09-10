<?php

namespace Drupal\im_commons\EventSubscriber;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_checkout\Event\CheckoutEvents;
use Drupal\commerce_order\Event\OrderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CheckoutCompleteSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[CheckoutEvents::COMPLETION][] = ['onCheckoutComplete'];
    return $events;
  }

  /**
   * React to the checkout complete event.
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $event
   */
  public function onCheckoutComplete(OrderEvent $event) {    
    // Get the order object from the event.
    $order = $event->getOrder();

    // Check if the user is logged in.
    $user = $order->getCustomer();
    if ($user->isAnonymous()) {
      return;
    }

    // Loop through the order items to check if the specific product was purchased.
    foreach ($order->getItems() as $order_item) {
      // The purchased entity is the product variation.
      $purchased_entity = $order_item->getPurchasedEntity();

      // Check if the product variation has the SKU field.
      if ($purchased_entity->hasField('sku')) {
        $sku = $purchased_entity->get('sku')->value;

        // Check if the purchased product is "kiosk".
        if ($sku === 'kiosk-yearly-sub-4001' || $sku === 'kiosk-monthly-sub-4001') {
          // Add the 'kiosk_owner' role to the user.
          $user->addRole('kiosk_owner');
          $user->save();
        }

        // Check if the purchased product is "subscriber".
        if ($sku === 'impossible-yearly-sub-0001' || $sku === 'impossible-monthly-sub-0001') {
          // Add the 'subscriber' role to the user.
          $user->addRole('subscriber');
          $user->save();
        }
      }
    }
    // Now suppress any messages that may have been generated.
    \Drupal::messenger()->deleteAll();
  }
}
