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
   * Override create to substitute in group id
   */
  public function create($entity){
    $entity = parent::create($entity);
    $context = $this->groupContext;
    // To-do: add in support for multiple groups
    $groupwrapper = $context->getGroupByName($entity->og_group_ref);
    $body = $entity->body;
    $tagterms = taxonomy_get_term_by_name($entity->field_tags);
    $tagterm = array_values($tagterms)[0];

    unset($entity->body);
    unset($entity->og_group_ref);
    unset($entity->field_format);
    unset($entity->field_tags);
    $wrapper = entity_metadata_wrapper('node', $entity, array('bundle' => 'dataset'));
    $wrapper->og_group_ref->set(array($groupwrapper->nid->value()));
    $wrapper->body->set(array('value' => $body));
    $wrapper->field_tags->set(array($tagterm->tid));

    return $wrapper;
  }

  /**
   * @Given datasets:
   */
  public function addDatasets(TableNode $datasetsTable) {
    parent::addMultipleFromTable($datasetsTable);
    // TO-DO: Should be delegated to an outside search context file for common use
    $index = search_api_index_load("datasets");
    $group_index = search_api_index_load("groups_di");
    foreach($this->entities as $entity) {
      $index->index(entity_load($this->entity_type, array($entity->getIdentifier())));
      $group_index->index(entity_load($this->entity_type, array($entity->getIdentifier())));
    }
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
   * @Then the Dataset search updates behind the scenes
   */
  public function theDatasetSearchUpdatesBehindTheScenes()
  {
    $index = search_api_index_load('datasets');
    $items =  search_api_get_items_to_index($index);
    search_api_index_specific_items($index, $items);
  }

  /**
   * Get Dataset by name
   *
   * @param $name
   * @return stdClass dataset or FALSE
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
