<?php

namespace Drupal\DKANExtension\Context;


use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;

/**
 * Defines application features from the specific context.
 */
class ResourceContext extends RawDKANEntityContext{

    public function __construct(){
        parent::__construct(
          'node',
          'resource',
          // note that this field is called "Groups" not "publisher" in the form, should the field name be updated?
          array('publisher' => 'og_group_ref', 'published' => 'status')
        );
    }

    /**
     * Creates resources from a table.
     *
     * @Given resources:
     */
    public function addResources(TableNode $resourcesTable){
        parent::addMultipleFromTable($resourcesTable);
    }
}
