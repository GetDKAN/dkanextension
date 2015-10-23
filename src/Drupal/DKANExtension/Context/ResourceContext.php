<?php

namespace Drupal\DKANExtension\Context;


use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;

class ResourceContext extends RawDKANEntityContext{

    public function __construct(){
        parent::__construct(array(
            'author' => 'author',
            'title' => 'title',
            'description' => 'body',
            'publisher' => 'og_group_ref',
            'published' => 'published',
            'resource format' => 'field_format',
        ),
            'resource',
            'node'
        );
    }

    /**
     * @Given resources:
     */
    public function addResources(TableNode $resourcesTable){
        parent::addMultipleFromTable($resourcesTable);
        // TO-DO: Should be delegated to an outside search context file for common use
        $index = search_api_index_load("datasets");
        $index->index($this->entities);
    }

    /**
     * @BeforeScenario
     */
    public function gatherContexts(BeforeScenarioScope $scope){
        parent::gatherContexts($scope);
        $environment = $scope->getEnvironment();
        $this->groupContext = $environment->getContext('Drupal\DKANExtension\Context\GroupContext');
    }

    /**
     * Override create to substitute in group id
     */
    public function create($entity){
        $entity = parent::create($entity);
        $context = $this->groupContext;
        // To-do: add in support for multiple groups
        $group = $context->getGroupByName($entity->og_group_ref);
        $ids['und'][0]['target_id'] = $group->nid;
        $entity->og_group_ref = $ids;

        // Should be delegated to another method?
        $ids = array();
        $terms = taxonomy_get_term_by_name($entity->field_format);
        foreach($terms as $term) {
            $ids['und'][0]['tid'] = $term->tid;
        }
        $entity->field_format = $ids;
        return $entity;
    }

}