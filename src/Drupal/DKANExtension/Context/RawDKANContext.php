<?php
namespace Drupal\DKANExtension\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\DrupalExtension\Context\DrupalContext;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Mink\Exception\UnsupportedDriverActionException as UnsupportedDriverActionException;
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\DriverException;
use Behat\Behat\Tester\Exception\PendingException;
use EntityFieldQuery;
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
   * Get node by title from Database.
   *
   * @param $title: title of the node.
   *
   * @return Node or FALSE
   */
  public function getNodeByTitle($title) {
    $query = new EntityFieldQuery();
    $query->entityCondition('entity_type', 'node')
      ->propertyCondition('title', $title)
      ->range(0, 1);
    $result = $query->execute();
    if (isset($result['node'])) {
      $nid = array_keys($result['node']);
      return entity_load('node', $nid);
    }
    return false;
  }

}
