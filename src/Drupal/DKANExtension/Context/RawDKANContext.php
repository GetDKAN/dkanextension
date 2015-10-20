<?php
namespace Drupal\DKANExtension\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\DrupalExtension\Context\DrupalContext;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Mink\Exception\UnsupportedDriverActionException as UnsupportedDriverActionException;
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\DriverException;
use Behat\Behat\Tester\Exception\PendingException;
use \stdClass;

/**
 * Defines application features from the specific context.
 */
class RawDKANContext extends RawDrupalContext implements SnippetAcceptingContext {

  /**
   * Initializes context.
   *
   * Every scenario gets its own context instance.
   * You can also pass arbitrary arguments to the
   * context constructor through behat.yml.
   */
  public function __construct() {
    // Set the default timezone to NY
    date_default_timezone_set('America/New_York');
  }

  /**
   * Get dataset nid by title from context.
   *
   * @param $nodeTitle title of the node.
   * @param $type type of nodo look for.
   *
   * @return Node ID or FALSE
   */
  private function getNidByTitle($nodeTitle, $type) {
    $context = array();
    switch ($type) {
      case 'dataset':
        $context = $this->datasets;
        break;
      case 'resource':
        $context = $this->resources;
    }

    foreach ($context as $key => $node) {
      if ($node->title == $nodeTitle) {
        return $key;
      }
    }
    return FALSE;
  }
}
