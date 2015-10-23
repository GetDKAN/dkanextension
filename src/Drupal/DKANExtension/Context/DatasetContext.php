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
      'published' => 'published',
      'resource format' => 'resource format',
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
   * Override create to substitute in group id
   */
  public function create($entity){
    $entity = parent::create($entity);
    $context = $this->groupContext;
    // To-do: add in support for multiple groups
    $group = $context->getGroupByName($entity->og_group_ref);

    unset($entity->og_group_ref);
    $wrapper = entity_metadata_wrapper('node', $entity, array('bundle' => 'dataset'));
    $wrapper->og_group_ref->set(array($group->nid));
    $entity = $wrapper->raw();

    return $entity;
  }

  /**
   * @Given datasets:
   */
  public function addDatasets(TableNode $datasetsTable) {
    parent::addMultipleFromTable($datasetsTable);
    // TO-DO: Should be delegated to an outside search context file for common use
    $index = search_api_index_load("datasets");
    $index->index($this->entities);
  }

  /**
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
   * @param $name
   * @return stdClass dataset or FALSE
   */
  public function getDatasetByName($name){
    foreach($this->entities as $dataset) {
      if ($dataset->title == $name) {
        return $dataset;
      }
    }
    return FALSE;
  }
}
