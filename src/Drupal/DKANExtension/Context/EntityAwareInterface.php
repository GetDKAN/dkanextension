<?php

namespace Drupal\DKANExtension\Context;

use Drupal\DKANExtension\ServiceContainer\EntityStore;
use Drupal\DrupalExtension\Context\DrupalAwareInterface;

interface EntityAwareInterface extends DrupalAwareInterface {

  /**
   * Sets Drupal instance.
   * @param $store
   * @return
   */
  public function setEntityStore(EntityStore $store);

}
