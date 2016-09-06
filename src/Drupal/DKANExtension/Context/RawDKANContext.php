<?php
namespace Drupal\DKANExtension\Context;

use Behat\Mink\Driver\GoutteDriver;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use Behat\Testwork\Environment\Environment;
use Drupal\DKANExtension\ServiceContainer\EntityStore;
use Drupal\DKANExtension\ServiceContainer\PageStore;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\DrupalExtension\Context\DrupalContext;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\DriverException;
use Behat\Behat\Tester\Exception\PendingException;
use EntityFieldQuery;
use \stdClass;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

/**
 * Defines application features from the specific context.
 */
class RawDKANContext extends RawDrupalContext implements DKANAwareInterface {

  /** @var  \Drupal\DrupalExtension\Context\MinkContext */
  protected $minkContext;

  /** @var  \Devinci\DevinciExtension\Context\JavascriptContext */
  protected $jsContext;

  /**
   * @var \Drupal\DKANExtension\Context\PageContext
   */
  protected $pageContext;
  /**
   * @var \Drupal\DKANExtension\Context\SearchAPIContext
   */
  protected $searchContext;
  /**
   * @var \Drupal\DKANExtension\ServiceContainer\EntityStore
   */
  protected $entityStore;
  /**
   * @var \Drupal\DKANExtension\ServiceContainer\PageStore
   */
  protected $pageStore;
  /**
   * @var  \Drupal\DrupalExtension\Context\DrupalContext
   */
  protected $drupalContext;
  /**
   * @var Session
   */
  protected $fakeSession;

  /**
   * @BeforeScenario @disablecaptcha
   */
  public function beforeCaptcha()
  {
    // Need to both disable the validation function for the captcha
    // AND disable the appearence of the captcha form field
    module_load_include('inc', 'captcha', 'captcha');
    variable_set('disable_captcha', TRUE);
    captcha_set_form_id_setting('user_login', 'none');
    captcha_set_form_id_setting('feedback_node_form', 'none');
    captcha_set_form_id_setting('comment_node_feedback_form', 'none');
  }

  /**
   * @AfterScenario @disablecaptcha
   */
  public function afterCaptcha()
  {
    module_load_include('inc', 'captcha', 'captcha');
    variable_set('disable_captcha', FALSE);
    captcha_set_form_id_setting('user_login', 'default');
    captcha_set_form_id_setting('feedback_node_form', 'default');
    captcha_set_form_id_setting('comment_node_feedback_form', 'default');
  }

  public function setEntityStore(EntityStore $entityStore) {
    $this->entityStore = $entityStore;
  }

  public function getEntityStore() {
    return $this->entityStore;
  }

  public function setPageStore(PageStore $pageStore) {
    $this->pageStore = $pageStore;
  }

  public function getPageStore() {
    return $this->pageStore;
  }

  /**
   * @BeforeScenario
   */
  public function gatherContexts(BeforeScenarioScope $scope) {
    /** @var Environment $environment */
    $environment = $scope->getEnvironment();
    $this->searchContext = $environment->getContext('Drupal\DKANExtension\Context\SearchAPIContext');
    $this->minkContext = $environment->getContext('Drupal\DrupalExtension\Context\MinkContext');
    // This context needs to be registered and hasn't been up to now. Don't load if we don't need it.
    //$this->drushContext = $environment->getContext('Drupal\DrupalExtension\Context\DrushContext');
    $this->jsContext = $environment->getContext('Devinci\DevinciExtension\Context\JavascriptContext');
    $this->drupalContext = $environment->getContext('Drupal\DrupalExtension\Context\DrupalContext');

  }

  /**
   * Get node by title from Database.
   *
   * @param $title: title of the node.
   *
   * @return Node or FALSE
   */
  public function getNodeByTitle($title) {
    $query = new EntityFieldQuery();
    $query->entityCondition('entity_type', 'node')
      ->propertyCondition('title', $title)
      ->range(0, 1);
    $result = $query->execute();
    if (isset($result['node'])) {
      $nid = array_keys($result['node']);
      return entity_load('node', $nid);
    }
    return false;
  }

  /**
   * Get the currently logged in user.
   */
  public function getCurrentUser() {
    // Rely on DrupalExtension to keep track of the current user.
    return $this->drupalContext->user;
  }

  public function visitPage($named_page, $sub_path = null) {

    $page = $this->getPageStore()->retrieve($named_page);
    if (!$page) {
      throw new \Exception("Named page '$named_page' doesn't exist.");
    }
    $path = ($sub_path) ? $page->getUrl() . "/$sub_path" : $page->getUrl();
    $session = $this->getSession();
    $session = $this->visit($path, $session);
    $this->assertOnUrl($path);

    return $session;
  }

  public function getStatusCode($session = null) {
    if (!$session) {
      $session = $this->getSession();
    }
    try {
      return $session->getStatusCode();
    } catch (UnsupportedDriverActionException $e) {
      // Driver doesn't support this so we have to guess based on the page text.
      $results = $session->getPage()->findAll('css', 'h1');
      if (empty($results)) {
        // No H1s?  Maybe we're on the a page like the front page the doesn't have them.
        if(empty($session->getPage()->find('css', '#main'))) {
          //Let's assume that's a 500 error.
          return 500;
        }
      }
      // Check each of the results.
      foreach ($results as $h1) {
        $title = strtolower($h1->getText());
        if ($title == 'access denied') {
          return 403;
        }
        elseif ($title == 'page not found') {
          return 404;
        }
      }
      // Otherwise assume 200.
      return 200;
    }
  }

  public function assertOnUrl($assert_url, $session = null){
    if (!$session) {
      $session = $this->getSession();
    }

    $current_url = $session->getCurrentUrl();
    // Support relative paths when on a "base_url" page. Otherwise assume a full url.
    $current_url = str_replace($this->getMinkParameter("base_url"), "", $current_url);

    // Remove hash part from url since it's widely used
    // for client side routing and this can make some
    // test fail.
    $current_url = strtok($current_url, "#");

    // This code was setup to ignore url get params, but we are using them for datasets, so ignore this for now.
    //$current_url = drupal_parse_url($current_url);
    //$current_url = $current_url['path'];
    if($current_url !== $assert_url){
      throw new \Exception("Current page is $current_url, but $assert_url expected.");
    }
  }

  public function assertOnPage($named_page){
    $page = $this->getPageStore()->retrieve($named_page);
    if (!$page) {
      throw new \Exception("Named page '$named_page' doesn't exist.");
    }
    $assert_url = $page->getUrl();
    $this->assertOnUrl($assert_url);
  }


  public function assertCanViewPage($named_page, $sub_path = null, $assert_code = null){
    $session = $this->visitPage($named_page, $sub_path);
    $code = $this->getStatusCode();

    // First check that a certain status code is expected.
    if (isset($assert_code)) {
      if ($assert_code !== $code) {
        throw new \Exception("Page {$session->getCurrentUrl()} code doesn't match $assert_code. CODE: $code");
      }
      return $code;
    }

    // Throw an exception if a non-successful code was found.
    if ($code < 200 || $code >= 500) {
      throw new \Exception("Page {$session->getCurrentUrl()} has an error. CODE: $code");
    }
    elseif ($code == 404) {
      throw new \Exception("Page {$session->getCurrentUrl()} not found. CODE: $code");
    }
    elseif ($code == 403) {
      throw new \Exception("Page {$session->getCurrentUrl()} is access denied. CODE: $code");
    }
    return $code;
  }

  /**
   * @return \Behat\Mink\Session
   */
  public function getSessionFake() {
    if (isset($this->fakeSession)) {
      $session = $this->fakeSession();
      //$session->reset();
      return $session;
    }
    $driver = new GoutteDriver();
    $session = new Session($driver);
    $session->start();
    $this->fakeSession = $session;
    return $session;
  }

  public function visit($url, $session = null) {
    if (!$session) {
      $session = $this->getSession();
    }
    $url = $this->locatePath($url);
    $session->visit($url);
    return $session;
  }

  public function assertCurrentPageCode($assert_code = 200) {
    $session = $this->getSession();
    $code = $this->getStatusCode();
    if ($code !== $assert_code) {
      throw new \Exception("Page {$session->getCurrentUrl()} code doesn't match. ASSERT: $assert_code CODE: $code");
    }
  }

  /**
   * Helper function to get current context.
   */
  function getRegion($region) {
    $session = $this->getSession();
    $regionObj = $session->getPage()->find('region', $region);
    if (!$regionObj) {
      throw new \Exception(sprintf('No region "%s" found on the page %s.', $region, $session->getCurrentUrl()));
    }
    return $regionObj;
  }

  /**
   * @Given I should see :number items of :item in the :region region
   */
  public function iShouldSeeItemsOfInTheRegion($number, $item, $region) {
    $regionObj = $this->getRegion($region);
    // Count the number of items in the region
    $count = count($regionObj->findAll('css', $item));
    if (!$count) {
      throw new \Exception(sprintf("No items found in the '%s' region.", $region));
    }
    else {
      if ($count != $number) {
        if ($count > $number) {
          throw new \Exception(sprintf("More than %s items were found in the '%s' region (%s).", $number, $region, $count));
        }
        else {
          throw new \Exception(sprintf("Less than %s items were found in the '%s' region (%s).", $number, $region, $count));
        }
      }
    }
  }

  /**
   * @Given I should see :number items of :item or more in the :region region
   */
  public function iShouldSeeItemsOfOrMoreInTheRegion($number, $item, $region) {
    $regionObj = $this->getRegion($region);
    // Count the number of items in the region
    $count = count($regionObj->findAll('css', $item));
    if (!$count) {
      throw new \Exception(sprintf("No items found in the '%s' region.", $region));
    }
    else {
      if ($count < $number) {
        throw new \Exception(sprintf("Less than %s items were found in the '%s' region (%s).", $number, $region, $count));
      }
    }
  }

  /**
   * @Then I should see :arg1 field
   */
  public function iShouldSeeField($arg1)
  {
    $session = $this->getSession();
    $page = $session->getPage();
    $field = $page->findField($arg1);
    if (!isset($field)) {
      throw new \Exception(sprintf("Field with the text '%s' not found", $arg1));
    }
  }

  /**
   * @Then I should not see :arg1 field
   */
  public function iShouldNotSeeField($arg1)
  {
    $session = $this->getSession();
    $page = $session->getPage();
    $field = $page->findField($arg1);
    if ($field) {
      throw new \Exception(sprintf("Field with the text '%s' is found", $arg1));
    }
  }

  /**
   * @Then the text :text should be visible in the element :element
   */
  public function theTextShouldBeVisible($text, $selector)
  {
    $element = $this->getSession()->getPage();
    $nodes = $element->findAll('css', $selector . ":contains('" . $text . "')");
    foreach ($nodes as $node) {
      if ($node->isVisible() === TRUE) {
        return;
      }
      else {
        throw new \Exception("Form item \"$selector\" with label \"$text\" is not visible.");
      }
    }
    throw new \Exception("Form item \"$selector\" with label \"$text\" not found.");
  }

  /**
   * @Then the text :text should not be visible in the element :element
   */
  public function theTextShouldNotBeVisible($text, $selector)
  {
    $element = $this->getSession()->getPage();
    $nodes = $element->findAll('css', $selector . ":contains('" . $text . "')");
    foreach ($nodes as $node) {
      if ($node->isVisible() === FALSE) {
        return;
      }
      else {
        throw new \Exception("Form item \"$selector\" with label \"$text\" is visible.");
      }
    }
    throw new \Exception("Form item \"$selector\" with label \"$text\" not found.");
  }


  /**
   * Check items count in workbench tree.
   *
   * @Given the workbench tree should contain :count elements or more
   */
  public function theWorkbenchTreeShouldContainCountElements($count)
  {
    $session = $this->getSession();
    $page = $session->getPage();
    $workflow_list = $page->find('css', 'ul.views-workflow-list');
    $workflow_list_items = array();
    // Check if any workflow list is found.
    if(isset($workflow_list)) {
      $workflow_list_items = $workflow_list->findAll('css', '.views-workflow-list-title');
    }
    if(count($workflow_list_items) < $count) {
      throw new Exception(sprintf("Workbench tree elements count is different then count provided!"));
    }
  }

  /**
   * @Then the :selector elements should be sorted in this order :order
   */
  public function theElementsShouldBeSortedInThisOrder($selector, $order)
  {
    $region = $this->getRegion("content");
    $items = $region->findAll('css', $selector);
    $actual_order = array();
    foreach ($items as $item) {
      if ($item->getText() !== "") {
        $actual_order[] = $item->getText();
      }
    }
    $order = explode(" > ", $order);
    if ($order !== $actual_order) {
      throw new Exception(sprintf("The elements were not sorted in the order provided."));
    }
  }

  /**
   * @Then I visit the link :selector
   */
  public function iVisitTheLink($selector)
  {
    $region = $this->getRegion("content");
    $items = $region->findAll('css', $selector);
    if (empty($items)) {
      throw new \Exception("Link '$selector' not found on the page.");
    }
    $url = reset($items)->getAttribute('href');
    $session = $this->getSession();
    $session->visit($this->locatePath($url));
  }

  /**
   * @Then I click the :link next to :title
   */
  public function iClickTheLinkNextToTitle($link, $title) {
    $items = $this->getSession()->getPage()->findAll('xpath', "//span[contains(@class,'views-dkan-workflow-tree-title')]/a[text()=' " . $title . "']/../../span[contains(@class, 'views-dkan-workflow-tree-action')]/a[text()='" . $link . "']");
    if (empty($items)) {
      throw new \Exception("Link '$link' not found on the page.");
    }
    $url = reset($items)->getAttribute('href');
    $session = $this->getSession();
    $session->visit($this->locatePath($url));
  }

  /**
   * @Given /^I wait for "([^"]*)" seconds$/
   */
  public function iWaitForSeconds($milliseconds)
  {
    $session = $this->getSession();
    $session->wait($milliseconds * 1000);
  }

}
