<?php

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Drupal\DKANExtension\Context\RawDKANEntityContext;

/**
 * Defines application features from the specific context.
 */
class BlogContext extends RawDKANEntityContext {

  /**
   * Initializes context.
   *
   * Every scenario gets its own context instance.
   * You can also pass arbitrary arguments to the
   * context constructor through behat.yml.
   */
  public function __construct() {
    parent::__construct(
      'node',
      'blog'
    );
  }

  /**
   * Creates blog entries from a table.
   *
   * @Given blog entries:
   */
  public function addBlogEntries(TableNode $blogEntriesTable) {
    parent::addMultipleFromTable($blogEntriesTable);
  }

}
