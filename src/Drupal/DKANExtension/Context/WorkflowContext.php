<?php
namespace Drupal\DKANExtension\Context;
namespace Drupal\DKANExtension\Context;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Drupal\DrupalExtension\Context\DrupalContext;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Mink\Exception\UnsupportedDriverActionException as UnsupportedDriverActionException;
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\DriverException;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Tester\Exception\PendingException;
use \stdClass;

/**
 * Defines application features from the specific context.
 */
class WorkflowContext extends RawDKANContext {

  /**
   * @Given I update the moderation state of :name to :state
   * @Given I update the moderation state of :name to :state on date :date
   *
   */
  public function updateModerationState($name, $state, $date = null) {
    /** @var \EntityDrupalWrapper $wrapper */
    $wrapper = $this->getEntityStore()->retrieve_by_name($name);
    if ($wrapper === FALSE) {
      throw new \Exception("No entity with the name '$name' was found. Make sure it's created in the step.");
    }
    if ($wrapper->type() !== 'node') {
      $entity_type = $wrapper->type();
      throw new \Exception("Only nodes types are supported by workbench_moderation, but $entity_type type given.");
    }
    if (!workbench_moderation_node_type_moderated($wrapper->getBundle())) {
      $types = implode(', ', workbench_moderation_moderate_node_types());
      throw new \Exception("Nodes type '{$wrapper->getBundle()}' is not a moderated type. Types enabled are [$types]'.");
    }

    $possible_states = workbench_moderation_state_labels();
    $state_key = array_search($state, $possible_states);
    if (!$state_key) {
      $possible_states = implode(", ", $possible_states);
      throw new \Exception("State '$state' is not available. All possible states are [$possible_states].");
    }

    $current_user = $this->getCurrentUser();
    if (!$current_user) {
      throw new \Exception("No user is logged in.");
    }

    $node = $wrapper->raw();
    workbench_moderation_node_data($node);
    $my_revision = $node->workbench_moderation['my_revision'];
    $next_states = workbench_moderation_states_next($my_revision->state, $current_user, $node);
    if (empty($next_states)) {
      $next_states = array();
    }
    if (!isset($next_states[$state_key])) {
      $next_states = implode(", ", $next_states);
      throw new \Exception("State '$possible_states[$state_key]' is not available to transition to. Transitions available to user '$current_user->name' are [$next_states]");
    }

    workbench_moderation_moderate($node, $state_key, $current_user->uid);

    // If a specific date is requested, then updated it after the fact.
    if (isset($date)) {
      $timestamp = strtotime($date, REQUEST_TIME);
      if (!$timestamp) {
        throw new \Exception("Error creating datetime from string '$date'");
      }

      db_update('workbench_moderation_node_history')
        ->fields(array(
          'stamp' => $timestamp,
        ))
        ->condition('nid', $node->nid, '=')
        ->condition('vid', $node->vid, '=')
        ->execute();
    }
  }

 /**
  * @Then I update the moderation state of :name to :state
  */
  public function assertModerationState($name, $state) {

  }


  public function getCurrentState($name) {
    $wrapper = $this->getEntityStore()->retrieve_by_name($name);
    if ($wrapper === FALSE) {
      throw new \Exception("No entity with the name '$name' was found. Make sure it's created in the step.");
    }
    if ($wrapper->type() !== 'node') {
      $entity_type = $wrapper->type();
      throw new \Exception("Only nodes types are supported by workbench_moderation, but $entity_type type given.");
    }
    if (!workbench_moderation_node_type_moderated($wrapper->getBundle())) {
      $types = implode(', ', workbench_moderation_moderate_node_types());
      throw new \Exception("Nodes type '{$wrapper->getBundle()}' is not a moderated type. Types enabled are [$types]'.");
    }

    $node = $wrapper->raw();
    workbench_moderation_node_data($node);

    $possible_states = workbench_moderation_state_labels();
    $state_key = array_search($state, $possible_states);
    if (!$state_key) {
      $possible_states = implode(", ", $possible_states);
      throw new \Exception("State '$state' is not available. All possible states are [$possible_states].");
    }
  }
}

