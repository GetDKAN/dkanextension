<?php
namespace Drupal\DKANExtension\Context;

use Drupal\DKANExtension\Context\RawDKANContext;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends RawDKANContext {

  // /******************************
  //  * HOOKS
  //  ******************************/

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

  // /****************************
  //  * HELPER FUNCTIONS
  //  ****************************/

  function getRegion($region) {
    $session = $this->getSession();
    $regionObj = $session->getPage()->find('region', $region);
    if (!$regionObj) {
      throw new \Exception(sprintf('No region "%s" found on the page %s.', $region, $session->getCurrentUrl()));
    }
    return $regionObj;
  }

  // /*****************************
  //  * CUSTOM STEPS
  //  *****************************/

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
  public function iWaitForSeconds($seconds)
  {
    $session = $this->getSession();
    $session->wait($seconds * 1000);
  }

}
