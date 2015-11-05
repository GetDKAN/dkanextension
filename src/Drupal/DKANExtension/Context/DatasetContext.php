<?php
namespace Drupal\DKANExtension\Context;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use SearchApiIndex;
use \stdClass;
use Symfony\Component\Console\Helper\Table;

/**
 * Defines application features from the specific context.
 */
class DatasetContext extends RawDKANEntityContext {

  public function __construct() {
    parent::__construct(array(
      'author' => 'author',
      'title' => 'title',
      'description' => 'body',
      'publisher' => 'og_group_ref',
      'published' => 'status',
      'tags' => 'field_tags',
    ),
      'dataset',
      'node'
    );
  }

  /**
   * @BeforeScenario
   */
  public function gatherContexts(BeforeScenarioScope $scope){
    parent::gatherContexts($scope);
    $environment = $scope->getEnvironment();
    $this->groupContext = $environment->getContext('Drupal\DKANExtension\Context\GroupContext');
  }

  /**
   * Sets the multi-fields for body, tags, and reference to this dataset's group.
   *
   * @param $entity - the stdClass entity to wrap
   * @return \EntityMetadataWrapper of the entity
   */
  public function wrap($entity){
    $context = $this->groupContext;
    // To-do: add in support for multiple groups
    $groupwrapper = $context->getGroupByName($entity->og_group_ref);
    $body = $entity->body;
    $tagterms = taxonomy_get_term_by_name($entity->field_tags);
    $tagterm = array_values($tagterms)[0];

    unset($entity->body);
    unset($entity->og_group_ref);
    unset($entity->field_tags);
    $wrapper = entity_metadata_wrapper('node', $entity, array('bundle' => 'dataset'));
    $wrapper->og_group_ref->set(array($groupwrapper->nid->value()));
    $wrapper->body->set(array('value' => $body));
    $wrapper->field_tags->set(array($tagterm->tid));

    return $wrapper;
  }

  /**
   * Creates datasets from a table.
   *
   * @Given datasets:
   */
  public function addDatasets(TableNode $datasetsTable) {
    parent::addMultipleFromTable($datasetsTable);
  }

  /**
   * Looks for a dataset in the dataset view with the given name on the current page.
   *
   * @Then I should see a dataset called :text
   *
   * @throws \Exception
   *   If region or text within it cannot be found.
   */
  public function iShouldSeeADatasetCalled($text)
  {
    $session = $this->getSession();
    $page = $session->getPage();
    $search_region = $page->find('css', '.view-dkan-datasets');
    $search_results = $search_region->findAll('css', '.views-row');
    $found = false;
    foreach( $search_results as $search_result ) {
      $title = $search_result->find('css', 'h2');
      if ($title->getText() === $text) {
        $found = true;
      }
    }
    if (!$found) {
      throw new \Exception(sprintf("The text '%s' was not found", $text));
    }
  }


  /**
   * Get Dataset by name
   *
   * @param $name - title of dataset
   * @return EntityMetadataWrapper dataset or FALSE
   */
  public function getDatasetByName($name){
    foreach($this->entities as $dataset) {
      if ($dataset->title->value() == $name) {
        return $dataset;
      }
    }
    return FALSE;
  }
}
