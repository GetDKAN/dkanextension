<?php
namespace Drupal\DKANExtension\Context;

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
class DKANContext extends DrupalContext {
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
   * @Then I should see :count :css items in the :region region
   */
  public function assertCSSItemsInTheRegion($count, $css, $region)
  {
    throw new \Exception(sprintf("I'm not sure this works as it should yet, don't use. --Frank"));
    $count = intval($count);
    $region = $this->minkContext->getRegion($region);
    $items = $region->findAll('css', $css);
    $num_found = sizeof($items);
    if($num_found !== $count){
      throw new \Exception(sprintf("Did not find %d %s items, found %d instead.", $count, $css, $num_found));
    }
  }

  function getCurrentUser() {
    if (isset($this->user)) {
      return $this->user;
    }
    else {
      return false;
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
   * @Then I should see the :user user page
   */
  public function assertSeeTheUserPage($user){
    $regionObj = $this->minkContext->getRegion('breadcrumb');
    $val = $regionObj->find('css', '.active-trail');
    $html = $val->getHtml();
    if($html !== $user){
      throw new \Exception('The user profile cannot be verified');
    }
    $currUser = $this->getCurrentUser();
    if($currUser->name === $user){
      $regionObj = $this->getSession()->getPage()->find('css', '.dkan-profile-page-user-name');
      $val = $regionObj->getText();
      if($val !== $user){
        throw new \Exception('The user profile cannot be verified');
      }
    }
    else{
      $regionObj = $this->getSession()->getPage()->find('css', '.pane-views-user-profile-fields-block');
      $val = $regionObj->find('xpath', '//h3[text()="'.$user.'"]');
      if($val === null){
        throw new \Exception('The user profile cannot be verified');
      }
    }
  }
}
