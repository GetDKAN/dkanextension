<?php
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
class DKANContext extends RawDKANContext {

  /** @var  \Drupal\DrupalExtension\Context\MinkContext */
  protected $minkContext;

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

  public function getMink() {
    return $this->minkContext;
  }

  /*****************************
   * CUSTOM STEPS
   *****************************/


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
   * @Then /^I should see a gravatar image in the "([^"]*)" region$/
   */
  public function iShouldSeeAGravatarImageInTheRegion($region)
  {
      $regionObj = $this->minkContext->getRegion($region);
      $elements = $regionObj->findAll('css', 'img');
      if (!empty($elements)) {
          foreach ($elements as $element) {
              if ($element->hasAttribute('src')) {
                  $value = $element->getAttribute('src');
                  if (preg_match('/\/\/www\.gravatar\.com\/avatar\/.*/', $value)) {
                      return;
          }
        }
      }
    }
    throw new \Exception(sprintf('The element gravatar link was not found in the "%s" region on the page %s', $region, $this->getSession()->getCurrentUrl()));

  }

  /**
   * @Then /^I should not see a gravatar image in the "([^"]*)" region$/
   */
  public function iShouldNotSeeAGravatarImageInTheRegion($region)
  {
      $regionObj = $this->minkContext->getRegion($region);
      $elements = $regionObj->findAll('css', 'img');
      $match = FALSE;
      if (!empty($elements)) {
          foreach ($elements as $element) {
              if ($element->hasAttribute('src')) {
                  $value = $element->getAttribute('src');
                  if (preg_match('/\/\/www\.gravatar\.com\/avatar\/.*/', $value)) {
                      $match = TRUE;
                    }
        }
      }
    }
    if ($match) {
          throw new \Exception(sprintf('The element gravatar link was found in the "%s" region on the page %s', $region, $this->getSession()->getCurrentUrl()));
    }
    else {
          return;
    }
  }

  /**
   * @Then I should see (the|a) user page
   * @Then I should see the :user user page
   */
  public function assertSeeTheUserPage($user = false){

    //TODO: This relies on the breadcrumb, can it be made better?
    $regionObj = $this->minkContext->getRegion('breadcrumb');
    $val = $regionObj->find('css', '.active-trail');
    $html = $val->getHtml();
    if($html !== $user){
      throw new \Exception('Could not find user name in breadcrumb. Text found:' . $val);
    }

    $regionObj = $this->minkContext->getRegion('user page');
    $val = $regionObj->getText();
    if($user !== false && strpos($val, $user) === false){
      throw new \Exception('Could not find username in the user page region. Text found:' . $val);
    }
  }

  /**
   * @Then I should see (the|a) user command center
   * @Then I should see the :user user command center
   */
  public function assertSeeUserCommandCenter($user = false){
    $regionObj = $this->minkContext->getRegion('user command center');
    $val = $regionObj->getText();
    if($user !== false && strpos($val, $user) === FALSE){
      throw new \Exception('Could not find username in the user command center region. Text found:' . $val);
    }
    //TODO: Consider checking for the elements that should be in the command center.
  }

  /**
   * @AfterScenario
   *
   * Delete any tempusers that were created outside of 'Given users'.
   */
  public function deleteTempUsers(AfterScenarioScope $scope) {
    if ($scope->getScenario()->hasTag('deleteTempUsers')) {
      // Get all users that start with tempUser*
      $results = db_query("SELECT uid from users where name like 'tempuser%%'");
      foreach ($results as $user) {
        user_delete($user->uid);
      }
    }
  }
}
