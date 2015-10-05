<?php
namespace Drupal\DKANExtension\Context;

use Behat\Behat\Context\Context as Context;
use Behat\Mink\Exception\UnsupportedDriverActionException as UnsupportedDriverActionException;
use Behat\Gherkin\Node\TableNode;

/**
 * Defines application features from the specific context.
 */
class PageContext extends Context {

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
          throw new Exception("Page $page_title ($url) visited, but it returned a non-2XX response code of $code.");
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
}
