<?php
namespace Drupal\DKANExtension\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\TableNode;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use \stdClass;

/**
 * Defines application features from the specific context.
 */
class RawDKANEntityContext extends RawDrupalContext implements SnippetAcceptingContext {

  // Store entities as EntityMetadataWrappers for easy property inspection.
  protected $entities = array();

  protected $entity_type;
  protected $bundle;
  protected $field_map;

  /**
   * @var \Drupal\DKANExtension\Context\PageContext
   */
  protected $pageContext;
  /**
   * @var \Drupal\DKANExtension\Context\SearchAPIContext
   */
  protected $searchContext;


  public function __construct($field_map, $bundle, $entity_type = 'node') {
    $this->field_map = $field_map;
    $this->bundle = $bundle;
    $this->entity_type = $entity_type;
  }

  /**
   * @BeforeScenario
   */
  public function gatherContexts(BeforeScenarioScope $scope) {
    $environment = $scope->getEnvironment();
    $this->pageContext = $environment->getContext('Drupal\DKANExtension\Context\PageContext');
    $this->searchContext = $environment->getContext('Drupal\DKANExtension\Context\SearchAPIContext');
  }

  /**
   * @AfterScenario
   */
  public function deleteAll(AfterScenarioScope $scope) {
    foreach ($this->entities as $entity) {
      // The behat user teardown deletes all the content of a user automatically,
      // so we want to get a fresh entity instead of relying on the wrapper
      // (or a bool that confirms it's deleted)

      $entities_to_delete = entity_load($this->entity_type, array($entity->getIdentifier()));

      if (!empty($entities_to_delete)){
        foreach ($entities_to_delete as $entity_to_delete) {
          $entity_to_delete = entity_metadata_wrapper($this->entity_type, $entity_to_delete);
          entity_delete($this->entity_type, $entity_to_delete->getIdentifier());
        }
      }
      $entity->clear();
    }

    // For Scenarios Outlines, EntityContext is not deleted and recreated
    // and thus the entities array is not deleted and houses stale entities
    // from previous examples, so we clear it here
    $this->entities = array();

    // Make sure that we process any index items if they were deleted.
    $this->searchContext->process();
  }

  /**
   * Get Entity by name
   *
   * @param $name
   * @return Content or FALSE
   */
  private function getByName($name) {
    foreach ($this->entities as $entity) {
      if ($entity->title == $name) {
        return $entity;
      }
    }
    return FALSE;
  }

  /**
   * Explode a comma separated string in a standard way.
   *
   */
  function explode_list($string) {
    $array = explode(',', $string);
    $array = array_map('trim', $array);
    return is_array($array) ? $array : array();
  }

  /**
   *
   * Helper function to create an entity.
   *
   * Takes a array of key-mapped values and creates a fresh entity
   * using the data provided. The array should correspond to the context's field_map
   *
   * @param $entity - the array of values to create an entity from
   * @return the stdClass entity, or FALSE if failed
   */
  public function create($entity) {
    return entity_create($this->entity_type, (array) $entity);
  }

  /**
   *
   * Helper function to wrap an entity.
   *
   * Builds an EntityMetadataWrapper using a provided entity from the
   * create function. This will be overridden by sub-contexts to re-populate the fields
   * with more specific metadata, such as multifields.
   *
   * @param $entity - the stdClass entity to wrap
   * @return \EntityMetadataWrapper of the entity
   */
  public function wrap($entity){
    return entity_metadata_wrapper($this->entity_type, $entity);
  }

  /**
   * Helper function to save an entity wrapper.
   *
   * Expects an EntityMetadataWrapper, calls its respective save function,
   * and adds the object to the context's entites array, for later usage.
   *
   * @param $wrapper - the wrapped entity to save
   * @return the saved EntityMetadataWrapper
   */
  public function save($wrapper) {
    $wrapper->save();

    $id = $wrapper->getIdentifier();

    // Add the created entity to the array so it can be deleted later.
    $this->entities[$id] = $wrapper;

    return $wrapper;
  }

  /**
   * Adds an entity's page to known pages.
   *
   * Takes an entity and acquires it's unique uri, then calls
   * the addPage routine from PageContext to save it along
   * with other saved pages.
   *
   * @param $entity - the entity to add a page for
   */
  public function addPage($entity) {
    $uri = entity_uri($this->entity_type, $entity);

    // Add the url to the page array for easy navigation.
    $this->pageContext->addPage(array(
      'title' => $entity->title,
      'url' => $uri['path']
    ));
  }


  /**
   * Creates entities from a given table.
   *
   * Builds key-mapped arrays from a TableNode matching this context's field map,
   * then cycles through each array to start the entity build routine for each
   * corresponding array. This function will be called by sub-contexts to generate
   * their entities.
   *
   * @param TableNode $entityTable - provided
   * @throws \Exception
   */
  public function addMultipleFromTable(TableNode $entityTable) {
    foreach($this->entitiesFromTableNode($entityTable) as $entity) {
      $this->add($entity);
    }
  }

  /**
   * Build routine for an entity.
   *
   * Given an array of key-mapped values, the build routine is as follows:
   * * 1. Create the entity based off the array given
   * * 2. Wrap the fresh entity in an EntityMetadataWrapper (and finish any data population)
   * * 3. Save the wrapped entity.
   * * 4. Add the uri page for the entity to the array of pages.
   *
   * @param $entity - the array of key-mapped values
   */
  public function add($entity) {
    $entity = $this->create($entity);
    $wrapper = $this->wrap($entity);
    $wrapper = $this->save($wrapper);
    $entity = entity_load($this->entity_type, array($wrapper->getIdentifier()));
    $entity = reset($entity);
    $this->addPage($entity);
    // Make sure that we process any search indexed items.
    $this->searchContext->process();
  }

  /**
   * Creates an array of key-mapped values.
   *
   * Takes an TableNode and builds an multi-dimensional array,
   * that has the keys as the first array and the mapped values, for each individual
   * entity, as the remaining arrays. Does some in-place substitutions for shared properties of
   * entities.
   *
   * @param TableNode $entityTable - table containing mapped values for context's entity
   * @return array of key-mapped values
   * @throws \Exception
   */
  function entitiesFromTableNode(TableNode $entityTable) {
    $entities = array();
    foreach ($entityTable as $entityHash) {
      $entity = new stdClass;
      foreach ($entityHash as $field => $value) {
        if (isset($this->field_map[$field])) {
          $drupal_field = $this->field_map[$field];
          $entity->$drupal_field = $value;
        }
        else {
          throw new \Exception(sprintf("Entity field %s doesn't exist, or hasn't been mapped.", $field));
        }
      }
      // Add additional defaults like "type", and map user id to author.
      if($this->bundle != NULL) {
        $entity->type = $this->bundle;
      }
      // If entity has an author, substitute in their uid
      if(isset($entity->author)) {
        $author = user_load_by_name($entity->author);
        $entity->uid = $author->uid;
      }
      // Convert the string status from table into a flip bit
      if($this->entity_type === 'node' && isset($entity->status)){
        $entity->status = $entity->status === "Yes" ? 1 : 0;
      }
      $entities[] = $entity;
    }
    return $entities;
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
    $uid = db_query('SELECT uid FROM users WHERE name = :user_name', array(':user_name' =>  $userName))->fetchField();
    if ($uid && $user = user_load($uid)) {
      return $user;
    }
    return FALSE;
  }
}
