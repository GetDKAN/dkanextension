<?php
namespace Drupal\DKANExtension\Context;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use SearchApiQuery;

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

  /**
   * @Then I should see the local preview link
   */
  public function iShouldSeeTheLocalPreviewLink()
  {
    $this->assertSession()->pageTextContains(variable_get('dkan_dataset_teaser_preview_label', '') . ' ' . t('Preview'));
  }

  /**
   * @Given I should see the first :number dataset items in :orderby :sortdirection order.
   */
  public function iShouldSeeTheFirstDatasetListInOrder($number, $orderby, $sortdirection){
    $number = (int) $number;
    // Search the list of datasets actually on the page (up to $number items)
    $dataset_list = array();
    $count = 0;
    while(($count < $number ) && ($row = $this->getSession()->getPage()->find('css', '.views-row-'.($count+1))) !== null ){
      $row = $row->find('css', 'h2');
      $dataset_list[] = $row->getText();
      $count++;
    }

    if ($count !== $number) {
      throw new \Exception("Couldn't find $number datasets on the page. Found $count.");
    }

    switch($orderby){
      case 'Date changed':
        $orderby = 'changed';
        break;
      case 'Title':
        $orderby = 'title';
        break;
      default:
        throw new \Exception("Ordering by '$orderby' is not supported by this step.");
    }

    $index = search_api_index_load('datasets');
    $query = new SearchApiQuery($index);

    $results = $query->condition('type', 'dataset')
      ->condition('status', '1')
      ->sort($orderby, strtoupper($sortdirection))
      ->range(0, $number)
      ->execute();
    $count = count($results['results']);
    if (count($results['results']) !== $number) {
      throw new \Exception("Couldn't find $number datasets in the database. Found $count.");
    }

    foreach($results['results'] as $nid => $result) {
      $dataset = node_load($nid);
      $found_title = array_shift($dataset_list);
      if ($found_title !== $dataset->title) {
        throw new \Exception("Does not match order of list, $found_title was next on page but expected $dataset->title");
      }
    }
  }

  /**
   * @Given /^I add a Dataset Filtered List$/
   */
  public function iAddADatasetFilteredList() {
    $add_button = $this->getXPathElement("//fieldset[@class='widget-preview panel panel-default'][3]//a");
    $add_button->click();
  }
}


  /**
   * @When I empty the resources field :locator
   *
   * Empty the 'Resources' autocomplete field on a Dataset form.
   */
  public function iEmptyTheResourcesField($locator) {
    $session = $this->getSession();
    $page = $session->getPage();

    $locator = $this->fixStepArgument($locator);

    $field = $page->find('xpath', '//div[@id="' . $locator . '"]');
    if ($field === NULL) {
      throw new \InvalidArgumentException(sprintf('Cannot find chosen field: "%s"', $locator));
    }

    $field_choices = $field->findAll('css', '.chosen-choices .search-choice');
    foreach($field_choices as $field_choice) {
      $remove_button = $field_choice->find('css', '.search-choice-close');
      if ($remove_button) {
        $remove_button->click();
      }
    }
  }

  /**
   * @Given /^I fill in the resources field "([^"]*)" with "([^"]*)"$/
   *
   * Fill in the 'Resources' field on a Dataset form.
   */
  public function iFillInTheResourcesFieldWith($field, $value) {
    $session = $this->getSession();
    $page = $session->getPage();

    $field = $this->fixStepArgument($field);
    $value = $this->fixStepArgument($value);

    $element = $page->findField($field);
    if (!$element) {
      throw new ElementNotFoundException($session, NULL, 'named', $field);
    }
    $page->fillField($field, $value);

    // Trigger all needed key events in order for the autocomplete to be triggered.
    // Just filling the field with a value is not enough.
    // TODO: Is there a better way to do this?
    $chars = str_split($value);
    $last_char = array_pop($chars);
    // Delete last char.
    $session->getDriver()->keyDown($element->getXpath(), 8);
    $session->getDriver()->keyUp($element->getXpath(), 8);
    // Re-add last char.
    $session->getDriver()->keyDown($element->getXpath(), $last_char);
    $session->getDriver()->keyUp($element->getXpath(), $last_char);
    $this->iWaitForSeconds(5);

    $title = $page->find(
      'xpath',
      $session->getSelectorsHandler()->selectorToXpath('xpath', '//li[.="' . $value . '"]')
    );
    $title->click();
  }
