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

        $body = $entity->body;
        $group = $context->getGroupByName($entity->og_group_ref);
        $terms = taxonomy_get_term_by_name($entity->field_format);
        $term = array_values($terms)[0];

        unset($entity->body);
        unset($entity->og_group_ref);
        unset($entity->field_format);
        $wrapper = entity_metadata_wrapper('node', $entity, array('bundle' => 'resource'));
        $wrapper->body->set(array('value' => $body));

        // To-do: add in support for multiple groups
        $wrapper->og_group_ref->set(array($group->nid));

        $wrapper->field_format->set($term->tid);
        $wrapper->save();

        return $entity;
    }

}