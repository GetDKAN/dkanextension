<?php
namespace Drupal\DKANExtension\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;
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
class DKANContext extends RawDrupalContext implements SnippetAcceptingContext {

  /**
   * Initializes context.
   *
   * Every scenario gets its own context instance.
   * You can also pass arbitrary arguments to the
   * context constructor through behat.yml.
   */
  public function __construct() {
    // Set the default timezone to NY
    date_default_timezone_set('America/New_York');
  }

  /**
   * @BeforeScenario
   */
  public function gatherContexts(BeforeScenarioScope $scope) {
    $environment = $scope->getEnvironment();

    $this->minkContext = $environment->getContext('Drupal\DrupalExtension\Context\MinkContext');
  }

  /**
   * @BeforeScenario @mail
   */
  public function beforeMail()
  {
    // Store the original system to restore after the scenario.
    echo("Setting Testing Mail System\n");
    $this->originalMailSystem = variable_get('mail_system', array('default-system' => 'DefaultMailSystem'));
    // Set the test system.
    variable_set('mail_system', array('default-system' => 'TestingMailSystem'));
    // Flush the email buffer.
    variable_set('drupal_test_email_collector', array());
  }

  /**
   * @AfterScenario @mail
   */
  public function afterMail()
  {
    echo("Restoring Mail System\n");
    // Restore the default system.
    variable_set('mail_system', $this->originalMailSystem);
    // Flush the email buffer.
    variable_set('drupal_test_email_collector', array());
  }

  /****************************
   * HELPER FUNCTIONS
   ****************************/

  /**
   * Explode a comma separated string in a standard way.
   *
   */
  function explode_list($string) {
    $array = explode(',', $string);
    $array = array_map('trim', $array);
    return is_array($array) ? $array : array();
  }

  /*****************************
   * CUSTOM STEPS
   *****************************/


  /**
   * @When I search for :term
   */
  public function iSearchFor($term) {
    $session = $this->getSession();
    $search_form_id = '#dkan-sitewide-dataset-search-form--2';
    $search_form = $session->getPage()->findAll('css', $search_form_id);
    if (count($search_form) == 1) {
      $search_form = array_pop($search_form);
      $search_form->fillField("search", $term);
      $search_form->pressButton("edit-submit--2");
      $results = $session->getPage()->find("css", ".view-dkan-datasets");
      if (!isset($results)) {
        throw new Exception("Search results region not found on the page.");
      }
    }
    else if(count($search_form) > 1) {
      throw new Exception("More than one search form found on the page.");
    }
    else if(count($search_form) < 1) {
      throw new Exception("No search form with the id of found on the page.");
    }
  }

  /**
   * @Then /^I should see the administration menu$/
   */
  public function iShouldSeeTheAdministrationMenu() {
    $xpath = "//div[@id='admin-menu']";
    // grab the element
    $element = $this->getXPathElement($xpath);
  }

  /**
   * @Then /^I should have an "([^"]*)" text format option$/
   */
  public function iShouldHaveAnTextFormatOption($option) {
    $xpath = "//select[@name='body[und][0][format]']//option[@value='" . $option . "']";
    // grab the element
    $element = $this->getXPathElement($xpath);
  }

  /**
   * Returns an element from an xpath string
   * @param  string $xpath
   *   String representing the xpath
   * @return object
   *   A mink html element
   */
  protected function getXPathElement($xpath) {
    // get the mink session
    $session = $this->getSession();
    // runs the actual query and returns the element
    $element = $session->getPage()->find(
        'xpath',
        $session->getSelectorsHandler()->selectorToXpath('xpath', $xpath)
    );
    // errors must not pass silently
    if (null === $element) {
      throw new \InvalidArgumentException(sprintf('Could not evaluate XPath: "%s"', $xpath));
    }
    return $element;
  }

  /**
   * @Then I should see :arg1 items in the :arg2 region
   */
  public function iShouldSeeItemsInTheRegion($arg1, $arg2)
  {
    $context = $this->minkContext;
    $region = $context->getRegion($arg2);
    $items = $region->findAll('css', '.views-row');

    $num = sizeof($items);

    if($num === 0){
      $items = $region->find('css', '.views-row-last');
      if(!empty($items)) $num = 2;
      else{
        $items = $region->find('css', '.views-row-first');
        if(!empty($items)) $num = 1;
      }
    }

    if($num !== intval($arg1)){
      throw new \Exception(sprintf("Did not find %d %s items, found %d instead.", $arg1, $arg2, sizeof($num)));
    }
  }

  /**
   * @Then I should see the :arg1 page
   */
  public function iShouldSeeThePage($arg1)
  {
    throw new PendingException();
  }
}g