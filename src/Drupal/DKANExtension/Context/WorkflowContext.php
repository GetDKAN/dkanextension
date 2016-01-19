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
   *
   */
  public function updateModerationStatus($name, $state) {
    /** @var \EntityDrupalWrapper $wrapper */
    $wrapper = $this->getEntityStore()->retrieve_by_name($name);
    if ($wrapper === FALSE) {
      throw new \Exception("No entity with the name '$name' was found. Make sure it's created in the step." );
    }
    if ($wrapper->type() !== 'node') {
      throw new \Exception("Only nodes types are supported by workbench_moderation, but $wrapper->type type given.");
    }
    if ( !workbench_moderation_node_type_moderated($wrapper->getBundle())) {
      $types = implode(', ', workbench_moderation_moderate_node_types());
      throw new \Exception("Nodes type '{$wrapper->getBundle()}' is not a moderated type. Types enabled are $types.");
    }
    $possible_states = workbench_moderation_state_labels();
    $next_states = workbench_moderation_states_next($current_state, $account = NULL, $node);
    if (! in_array($state, $possible_states)) {
      throw new \Exception("State '$state' is not available. States enabled are $possible_states.");
    }
    #workbench_moderation_states_next($current_state, $account = NULL, $node);
    #workbench_moderation_moderate($node, $state, $uid = NULL)
    #workbench_moderation_set_state_action($node, $context)
  }

}
