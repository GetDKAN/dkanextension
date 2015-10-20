<?php
namespace Drupal\DKANExtension\Context;

use Behat\Gherkin\Node\TableNode;
use \stdClass;

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
      'group',
      'node'
    );
  }

  /**
   * @Given datasets:
   */
  public function addDatasets(TableNode $datasetsTable) {
    parent::addMultipleFromTable($datasetsTable);
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
}
