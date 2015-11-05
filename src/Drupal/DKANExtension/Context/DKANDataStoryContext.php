<?php

namespace Drupal\DKANExtension\Context;

use Behat\Gherkin\Node\TableNode;

/**
 * Defines application features from the specific context.
 */
class DKANDataStoryContext extends RawDKANEntityContext{

    public function __construct(){
        parent::__construct(array(
                'title' => 'title',
                'author' => 'author',
                'status' => 'status',
                'description' => 'body',
                'tags' => 'field_tags',
            ),
            'dkan_data_story',
            'node'
        );
    }

    /**
     * Creates data stories from table.
     *
     * @Given data stories:
     */
    public function addDataStories(TableNode $datastoriestable){
        parent::addMultipleFromTable($datastoriestable);
    }

    /**
     * Sets the multi fields for body and tags.
     *
     * @param $entity - the stdClass entity to wrap
     * @return \EntityMetadataWrapper of the entity
     */
    public function wrap($entity){

        $body = $entity->body;

        //support multiple tags?
        $terms = taxonomy_get_term_by_name($entity->field_tags);
        $term = array_values($terms)[0];

        unset($entity->field_tags);
        unset($entity->body);
        $wrapper = entity_metadata_wrapper('node', $entity, array('bundle' => 'dkan_data_story'));
        $wrapper->body->set(array('value' => $body));
        $wrapper->field_tags->set(array($term->tid));

        return $wrapper;
    }
}
