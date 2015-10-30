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

  public function create($entity) {
    return entity_create($this->entity_type, (array) $entity);
  }

  public function wrap($entity){
    return entity_metadata_wrapper($this->entity_type, $entity);
  }

  public function save($wrapper) {
    $wrapper->save();

    $id = $wrapper->getIdentifier();

    // Add the created entity to the array so it can be deleted later.
    $this->entities[$id] = $wrapper;

    return $wrapper;
  }

  public function addPage($entity) {
    $uri = entity_uri($this->entity_type, $entity);

    // Add the url to the page array for easy navigation.
    $this->pageContext->addPage(array(
      'title' => $entity->title,
      'url' => $uri['path']
    ));
  }


  public function addMultipleFromTable(TableNode $entityTable) {
    foreach($this->entitiesFromTableNode($entityTable) as $entity) {
      $this->add($entity);
    }
  }

  public function add($entity) {
    $entity = $this->create($entity);
    $wrapper = $this->wrap($entity);
    $wrapper = $this->save($wrapper);
    $entity = reset(entity_load($this->entity_type, array($wrapper->getIdentifier())));
    $this->addPage($entity);
  }

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
