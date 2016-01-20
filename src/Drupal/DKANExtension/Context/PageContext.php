<?php
namespace Drupal\DKANExtension\Context;

use Behat\Gherkin\Node\TableNode;
use Drupal\DKANExtension\ServiceContainer\Page;

/**
 * Defines application features from the specific context.
 */
class PageContext extends RawDKANContext {

  /**
   * @Given pages:
   */
  public function addPages(TableNode $pagesTable) {
    foreach ($pagesTable as $pageHash) {
      if (!isset($pageHash['name'])) {
        throw new \Exception('name value missing for page.');
      }
      if (!isset($pageHash['url'])) {
        throw new \Exception('url value missing for page.');
      }
      $page = new Page($pageHash['name'], $pageHash['url']);
      $this->getPageStore()->store($page);
    }
  }

  /**
   * @Given I am on (the) :page page
   */
  public function givenOnPage($page) {
    $this->visitPage($page);
  }

  /**
   * @Then I should be on (the) :page page
   */
  public function assertOnPage($page){
    parent::assertOnPage($page);
  }

  /**
   * @Given I should be able to access the :page_title page
   */
  public function iShouldBeAbleToAccessPage($page_title) {
    $this->assertCanViewPage($page_title);
  }

  /**
   * @Given I should be denied access to the :page_title page
   */
  public function iShouldBeDeniedToAccessPage($page_title) {
    // Assume mean getting a 403 (Access Denied), not just missing or an error.
    $this->assertCanViewPage($page_title, null, 403);
  }

  /**
   * @Given The :page_title page should not be found
   */
  public function pageShouldBeNotFound($page_title) {
    $this->assertCanViewPage($page_title, null, 404);
  }

  /**
   * @Given I should be able to edit :named_entity
   */
  public function iShouldBeAbleToEdit($named_entity) {
    $this->assertCanViewPage($named_entity, "edit");
  }

  /**
   * @Given I should not be able to edit :named_entity
   */
  public function iShouldNotBeAbleToEdit($named_entity) {
    // Assume mean getting a 403 (Access Denied), not just missing or an error.
    $this->assertCanViewPage($named_entity, "edit", 403);
  }

  /**
   * @Given I should be able to delete :named_entity
   */
  public function iShouldBeAbleToDelete($named_entity) {
    // Assume mean getting a 403 (Access Denied), not just missing or an error.
    $this->assertCanViewPage($named_entity, "delete");
  }

  /**
   * @Given I should not be able to delete :named_entity
   */
  public function iShouldNotBeAbleToDelete($named_entity) {
    // Assume mean getting a 403 (Access Denied), not just missing or an error.
    $this->assertCanViewPage($named_entity, "delete", 403);
  }

}
