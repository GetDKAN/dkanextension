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
    parent::__construct(
      'node',
      'dataset'
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
}
