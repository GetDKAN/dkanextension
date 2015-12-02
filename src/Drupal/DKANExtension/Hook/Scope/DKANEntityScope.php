<?php
/**
 * @file
 * Node scope.
 */
namespace Drupal\DKANExtension\Hook\Scope;

use Drupal\DrupalExtension\Hook\Scope\BaseEntityScope as BaseEntityScope;

/**
 * Represents an Entity hook scope.
 */
abstract class DKANEntityScope extends BaseEntityScope {

  const BEFORE = 'entity.create.before';
  const AFTER = 'entity.create.after';

}
