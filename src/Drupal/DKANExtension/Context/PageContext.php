<?php
namespace Drupal\DKANExtension\Context;

use Behat\Behat\Context\Context as Context;
use Behat\Mink\Exception\UnsupportedDriverActionException as UnsupportedDriverActionException;
use Behat\Gherkin\Node\TableNode;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use EntityFieldQuery;

/**
 * Defines application features from the specific context.
 */
class PageContext extends RawDrupalContext {

  // Store pages to be referenced in an array.
  protected $pages = array();

  /**
   * Get node by title from Database.
   *
   * @param $title: title of the node.
   * @param $reset: reset drupal cache before looking for the node.
   *
   * @return Node ID or FALSE
   */
  function getNodeByTitle($title) {
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
   * @Given I should not be able to access :page_title
   */
  public function iShouldNotBeAbleToAccessPage($page_title) {
    if (isset($this->pages[$page_title])) {
      $session = $this->getSession();
      $url = $this->pages[$page_title]['url'];
      $session->visit($this->locatePath($url));
      try {
        $code = $session->getStatusCode();
        if ($code == 200) {
          throw new \Exception("200 OK: the page is accessible.");
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
   * @Given I should be able to edit :page
   */
  public function iShouldBeAbleToEditPage($page) {
    $node = $this->getNodeByTitle($page);
    if(!$node) {
      throw new \Exception(sprintf($page . " node not found."));
    }

    $session = $this->getSession();
    $url = "/node/" . $node->nid . "/edit";
    $session->visit($this->locatePath($url));
    $code = $session->getStatusCode();
    if ($code == 403) {
      throw new \Exception("403 Forbidden: the server refused to respond.");
    }
  }

  /**
   * @Given I should not be able to edit :page
   */
  public function iShouldNotBeAbleToEditPage($page) {
    $node = $this->getNodeByTitle($page);
    if(!$node) {
      throw new \Exception(sprintf($page . " node not found."));
    }

    $session = $this->getSession();
    $url = "/node/" . $node->nid . "/edit";
    $session->visit($this->locatePath($url));
    $code = $session->getStatusCode();
    if ($code == 200) {
      throw new \Exception("200 OK: the page is accessible.");
    }
  }

  /**
   * @Given I should be able to delete :page
   */
  public function iShouldBeAbleToDeletePage($page) {
    $node = $this->getNodeByTitle($page);
    if(!$node) {
      throw new \Exception(sprintf($page . " node not found."));
    }

    $session = $this->getSession();
    $url = "/node/" . $node->nid . "/delete";
    $session->visit($this->locatePath($url));
    $code = $session->getStatusCode();
    if ($code == 403) {
      throw new \Exception("403 Forbidden: the server refused to respond.");
    }
  }

  /**
   * @Given I should not be able to delete :page
   */
  public function iShouldNotBeAbleToDeletePage($page) {
    $node = $this->getNodeByTitle($page);
    if(!$node) {
      throw new \Exception(sprintf($page . " node not found."));
    }

    $session = $this->getSession();
    $url = "/node/" . $node->nid . "/delete";
    $session->visit($this->locatePath($url));
    $code = $session->getStatusCode();

    if ($code == 200) {
      throw new \Exception("200 OK: the page is accessible.");
    }
  }

}
