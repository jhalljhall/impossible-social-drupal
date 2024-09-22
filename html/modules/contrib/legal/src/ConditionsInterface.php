<?php

namespace Drupal\legal;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a Terms & Conditions Conditions entity.
 *
 * @ingroup legal
 */
interface ConditionsInterface extends ContentEntityInterface, EntityOwnerInterface {

}
