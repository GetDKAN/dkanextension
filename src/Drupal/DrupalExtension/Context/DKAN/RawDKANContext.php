<?php

namespace Drupal\DrupalExtension\Context;

use Behat\Behat\Tester\Exception\PendingException;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;

/**
 * Provides pre-built step definitions for interacting with Drupal.
 */
class RawDKANContext extends RawDrupalContext implements SnippetAcceptingContext
{
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
 public function addPage($page) {
    $this->pages[$page['title']] = $page;
  }
  /**
   * Get Group by name
   *
   * @param $name
   * @return Group or FALSE
   */
  private function getGroupByName($name) {
    foreach($this->groups as $group) {
      if ($group->title == $name) {
        return $group;
      }
    }
    return FALSE;
  }
  /**
   * Get Group Role ID by name
   *
   * @param $name
   * @return Group Role ID or FALSE
   */
  private function getGroupRoleByName($name) {
    $group_roles = og_get_user_roles_name();
    return array_search($name, $group_roles);
  }
  /**
   * Get Membership Status Code by name
   *
   * @param $name
   * @return Membership status code or FALSE
   */
  private function getMembershipStatusByName($name) {
    switch($name) {
      case 'Active':
        return OG_STATE_ACTIVE;
        break;
      case 'Pending':
        return OG_STATE_PENDING;
        break;
      case 'Blocked':
        return OG_STATE_BLOCKED;
        break;
      default:
        break;
    }
    return FALSE;
  }
  /**
   * Explode a comma separated string in a standard way.
   *
   */
  function explode_list($string) {
    $array = explode(',', $string);
    $array = array_map('trim', $array);
    return is_array($array) ? $array : array();
  }


}
