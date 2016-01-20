<?php
namespace Drupal\DKANExtension\Context;

use \stdClass;

/**
 * Defines application features from the specific context.
 */
class WorkflowContext extends RawDKANContext {

  /**
   * @Given I update the moderation state of :named_entity to :state
   * @Given I update the moderation state of :named_entity to :state on date :date
   *
   * Transition a Moderated Node from one state to another.
   *
   * @param String $named_entity A named entity stored in the entity store.
   * @param String $state The state that you want to transition to.
   * @param String|null $date A valid php datetime string. Supports relative dates.
   * @throws \Exception
   */
  public function transitionModerationState($named_entity, $state, $date = null) {

    $node = $this->getModerationNode($named_entity);

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

    $my_revision = $node->workbench_moderation['my_revision'];
    $next_states = workbench_moderation_states_next($my_revision->state, $current_user, $node);
    if (empty($next_states)) {
      $next_states = array();
    }
    if (!isset($next_states[$state_key])) {
      $next_states = implode(", ", $next_states);
      throw new \Exception("State '$possible_states[$state_key]' is not available to transition to. Transitions available to user '$current_user->name' are [$next_states]");
    }

    // This function actually updates the transition.
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
   * @Then the moderation state of :name should be :state
   *
   * Assert the moderation state of a named entity.
   *
   * @param String $name A named entity (title)
   * @param String $state The moderation state the node should be currently at.
   * @throws \Exception
   */
  public function assertModerationState($name, $state) {

    $possible_states = workbench_moderation_state_labels();
    $state_key = array_search($state, $possible_states);
    if (!$state_key) {
      $possible_states = implode(", ", $possible_states);
      throw new \Exception("State '$state' is not available. All possible states are [$possible_states].");
    }

    $current_state_key = $this->getModerationState($name);
    if ($current_state_key !== $state_key) {
      throw new \Exception("State is not '$state', but instead it's $possible_states[$current_state_key].");
    }
  }

  /**
   * Get the current moderation state of a named node.
   *
   * @param String $name A named entity in the entity store.
   * @return String state_key
   * @throws \Exception
   */
  public function getModerationState($name) {
    $node = $this->getModerationNode($name);
    $my_revision = $node->workbench_moderation['my_revision'];
    return $my_revision->$my_revision->state;
  }

  /**
   * Grab a named node from the entity store and add moderation fields to it.
   *
   * @param String $name A named entity in the entity store.
   * @return \StdClass Node with additional moderation fields.
   * @throws \Exception
   */
  public function getModerationNode($name) {
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

    $node = $wrapper->raw();
    workbench_moderation_node_data($node);

    return $node;
  }
}

