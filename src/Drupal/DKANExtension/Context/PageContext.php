<?php
namespace Drupal\DKANExtension\Context;

use Behat\Behat\Context\Context as Context;
use Behat\Mink\Exception\UnsupportedDriverActionException as UnsupportedDriverActionException;
use Behat\Gherkin\Node\TableNode;
use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Defines application features from the specific context.
 */
class PageContext extends RawDrupalContext {

  // Store pages to be referenced in an array.
  protected $pages = array();

  /**
   * Add page to context.
   *
   * @param $page
   */
  public function addPage($page) {
    $this->pages[$page['title']] = $page;
  }

  /**
   * @Given pages:
   */
  public function addPages(TableNode $pagesTable) {
    foreach ($pagesTable as $pageHash) {
      // @todo Add some validation.
      $this->addPage($pageHash);
    }
  }

  /**
   * @Given I am on (the) :page page
   */
  public function iAmOnPage($page_title) {
    if (isset($this->pages[$page_title])) {
      $session = $this->getSession();
      $url = $this->pages[$page_title]['url'];
      $session->visit($this->locatePath($url));
      try {
        $code = $session->getStatusCode();
        if ($code < 200 || $code >= 300) {
          throw new \Exception("Page $page_title ($url) visited, but it returned a non-2XX response code of $code.");
        }
      } catch (UnsupportedDriverActionException $e) {
        // Some drivers don't support status codes, namely Selenium2Driver so
        // just drive on.
      }
    }
    else {
      throw new \Exception("Page $page_title not found in the pages array, was it added?");
    }
  }

  /**
   * @Then I should be on (the) :page page
   */
  public function assertOnPage($page_title){
    if(!isset($this->pages[$page_title])){
      throw new \Exception("Named page $page_title doesn't exist.");
    }
    $current_url = $this->getSession()->getCurrentUrl();
    // Support relative paths when on a "base_url" page. Otherwise assume a full url.
    $current_url = str_replace($this->getMinkParameter("base_url"), "", $current_url);

    $current_url = drupal_parse_url($current_url);
    $current_url = $current_url['path'];

    $url = $this->pages[$page_title]['url'];
    if($current_url !== $url){
      throw new \Exception("Current page is $current_url, but $url expected.");
    }
  }
}
