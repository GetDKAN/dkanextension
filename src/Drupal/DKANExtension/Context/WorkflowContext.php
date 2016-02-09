<?php
namespace Drupal\DKANExtension\Context;

use \stdClass;

/**
 * Defines application features from the specific context.
 */
class WorkflowContext extends RawDKANContext {

  protected $old_global_user;

  /**
   * @Given I update the moderation state of :named_entity to :state
   *
   * Transition a Moderated Node from one state to another.
   *
   * @param String $named_entity A named entity stored in the entity store.
   * @param String $state The state that you want to transition to.
   * @throws \Exception
   */
  public function transitionModerationState($named_entity, $state) {
    $session = $this->getSession();
    $page = $session->getPage();
    switch ($state) {
      // I want to move this to draft then I should click in a Reject button in
      // the Needs Review page.
      case 'Draft':
        $tab = $this->getPageStore()->retrieve('Needs Review');
        $button = 'Reject';
        break;
      // I want to move this to Needs Review then I should click in a Submit for Review
      // button in the Draft page.
      case 'Needs Review':
        $tab = $this->getPageStore()->retrieve('My Drafts');
        $button = 'Submit for Review';
        break;
      // I want to move this to Published then I should click in a Publish
      // button in the Needs Review page.
      case 'Published':
        $tab = $this->getPageStore()->retrieve('Needs Review');
        $button = 'Publish';
        break;
    }

    $this->visit($tab->getUrl());
    $submit = $page->find('xpath', '//div[contains(@class,"item-content") and contains(.,"' . $named_entity . '")]/span/a[contains(.,"' . $button . '")]');
    if(null === $submit) {
      throw new \Exception("Couldn't find the button '$button' for the node '$node'");
    }
    $submit->click();
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

  /**
   * @beforeDKANEntityCreate
   */
  public function setGlobalUserBeforeEntity(\Drupal\DKANExtension\Hook\Scope\BeforeDKANEntityCreateScope $scope) {
    // Don't do anything if workbench isn't enabled or this isn't a node.
    $wrapper = $scope->getEntity();
    if (!function_exists('workbench_moderation_moderate_node_types') || $wrapper->type() !== 'node'){
      return;
    }
    $types = workbench_moderation_moderate_node_types();
    $node_type = $wrapper->getBundle();

    // Also don't do anything if this isn't a moderation type.
    if (!in_array($node_type, $types)) {
      return;
    }

    // IF the author is set (there was a logged in user or it was set during creation)
    // See RawDKANEntity::pre_save()
    if (isset($wrapper->author)) {
      // Then set the global user so that stupid workbench is happy.
      global $user;
      // Save a backup of the user (should be anonymous)
      $this->old_global_user = $user;
      $user = $wrapper->author->value();
    }
  }

  /**
   * @afterDKANEntityCreate
   */
  public function removeGlobalUserAfterEntity(\Drupal\DKANExtension\Hook\Scope\AfterDKANEntityCreateScope $scope) {
    // After we've created the entity, set it back the the old global user (anon) so it doesn't pollute other things.
    if (isset($this->old_global_user)) {
      global $user;
      $user = $this->old_global_user;
    }
  }
}

