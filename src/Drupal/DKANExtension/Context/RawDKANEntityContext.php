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

  // Store pages to be referenced in an array.
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
      $entity->delete();
    }
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

  public function save($entity) {
    $entity->save();

    $id = $entity->getIdentifier();

    // Add the created entity to the array so it can be deleted later.
    $this->entities[$id] = $entity;

    return $entity;
  }

  public function addPage($entity) {
    list($path, $url_options) = entity_uri($this->entity_type, $entity);

    // Add the url to the page array for easy navigation.
    $this->pageContext->addPage(array(
      'title' => $entity->title,
      'url' => $path
    ));
  }


  public function addMultipleFromTable(TableNode $entityTable) {
    foreach($this->entitiesFromTableNode($entityTable) as $entity) {
      $this->add($entity);
    }
  }

  public function add($entity) {
    $entity = $this->create($entity);
    $this->save($entity);
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
      if(isset($entity->author)) {
        $author = user_load_by_name($entity->author);
        $entity->uid = $author->uid;
      }
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
