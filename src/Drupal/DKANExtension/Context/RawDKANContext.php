<?php
namespace Drupal\DKANExtension\Context;

use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Testwork\Environment\Environment;
use Drupal\DKANExtension\ServiceContainer\EntityStore;
use Drupal\DKANExtension\ServiceContainer\PageStore;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;


/**
 * Defines application features from the specific context.
 */
class RawDKANContext extends RawDrupalContext implements DKANAwareInterface {

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
  }

  /**
   * Check toolbar if this->user isn't working.
   */
  public function getCurrentUser() {
    if ($this->user) {
      return $this->user;
    }
    $session = $this->getSession();
    $page = $session->getPage();
    $xpath = $page->find('xpath', "//div[@class='content']/span[@class='links']/a[1]");
    $userName = $xpath->getText();
    $uid = db_query('SELECT uid FROM users WHERE name = :user_name', array(':user_name' => $userName))->fetchField();
    if ($uid && $user = user_load($uid)) {
      return $user;
    }
    return FALSE;
  }

  public function visitPage($page_title) {
    $page = $this->getPageStore()->retrieve($page_title);
    if (!isset($page)) {
      throw new \Exception("Page $page_title not found in the pages array, was it added?");
    }
    $session = $this->getSession();
    $session->visit($this->locatePath($page->getUrl()));
    $this->assertOnPage($page_title);
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
      $h1 = $session->getPage()->find('css', 'h1');
      if (!$h1 || !$title = $h1->getText()) {
        // No H1?  Let's assume that's a 500 error.
        return 500;
      }
      $title = strtolower($title);
      if ($title == 'access denied') {
        return 403;
      };
      if ($title == 'page not found') {
        return 404;
      };
      // Otherwise assume 200.
      return 200;
    }
  }

  public function assertOnUrl($assert_url){
    $current_url = $this->getSession()->getCurrentUrl();
    // Support relative paths when on a "base_url" page. Otherwise assume a full url.
    $current_url = str_replace($this->getMinkParameter("base_url"), "", $current_url);

    $current_url = drupal_parse_url($current_url);
    $current_url = $current_url['path'];
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

  public function assertCanViewPage($named_page, $subpath = null, $assert_code = null){
    $page = $this->getPageStore()->retrieve($named_page);
    if (!$page) {
      throw new \Exception("Named page '$named_page' doesn't exist.");
    }
    $path = ($subpath) ? $page->getUrl() . "/$subpath" : $page->getUrl();
    $session = $this->getSession();
    $session->visit($path);
    $this->assertOnUrl($path);
    $code = $this->getStatusCode($session);
    if (isset($assert_code) && $assert_code !== $code) {
      throw new \Exception("Page {$session->getCurrentUrl()} code doesn't match $assert_code. CODE: $code");
    }
    if ($code < 200 || $code >= 500) {
      throw new \Exception("Page {$session->getCurrentUrl()} has an error. CODE: $code");
    }
    if ($code == 404) {
      throw new \Exception("Page {$session->getCurrentUrl()} not found. CODE: $code");
    }
    if ($code == 403) {
      throw new \Exception("Page {$session->getCurrentUrl()} is access denied. CODE: $code");
    }
    return $code;
  }

}
