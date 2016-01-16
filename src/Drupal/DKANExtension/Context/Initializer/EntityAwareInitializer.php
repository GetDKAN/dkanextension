<?php

namespace Drupal\DKANExtension\Context\Initializer;

use Behat\Behat\Context\Initializer\ContextInitializer;
use Behat\Behat\Context\Context;
use Drupal\DKANExtension\Context\EntityAwareInterface;
use Drupal\DrupalExtension\Context\RawDrupalContext;

class EntityAwareInitializer extends RawDrupalContext implements ContextInitializer {
  private $entityStore, $parameters;

  public function __construct($entityStore, array $parameters) {
    $this->entityStore = $entityStore;
    $this->parameters = $parameters;
  }

  /**
   * {@inheritdocs}
   */
  public function initializeContext(Context $context) {

    // All contexts are passed here, only RawDKANEntityContext is allowed.
    if (!$context instanceof EntityAwareInterface) {
      return;
    }
    $context->setEntityStore($this->entityStore);

    // Add all parameters to the context.
    //$context->setParameters($this->parameters);
  }

}
