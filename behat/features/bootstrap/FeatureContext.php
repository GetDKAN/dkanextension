<?php
/**
 * @file
 * Feature context.
 */
// Contexts.
use Drupal\DrupalExtension\Context;
// Helpers.
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
/**
 * Class FeatureContext.
 */
class FeatureContext extends RawDKANContext {
  /**
   * Initializes context.
   *
   * Every scenario gets its own context instance.
   * You can also pass arbitrary arguments to the
   * context constructor through behat.yml.
   */
  public function __construct() {
  }
}
