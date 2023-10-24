<?php

namespace Drupal\commerce_funds\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $config = \Drupal::config('commerce_funds.settings')->get('global');

    if (!empty($config['disable_funds_forms'])) {
      foreach ($config['disable_funds_forms'] as $form_name) {
        if ($form_name) {
          if ($collection->get('commerce_funds.' . $form_name)) {
            $collection->remove(['commerce_funds.' . $form_name]);
          }
        }
      }
    }
  }

}
