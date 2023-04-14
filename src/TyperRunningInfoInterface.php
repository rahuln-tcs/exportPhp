<?php

namespace Drupal\tyres_running_info;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a tyres_running_info entity type.
 */
interface TyperRunningInfoInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
