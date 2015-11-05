<?php
namespace Drupal\DKANExtension\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\TableNode;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use \stdClass;
use Symfony\Component\Config\Definition\Exception\Exception;

/**
 * Defines application features from the specific context.
 */
class RawDKANEntityContext extends RawDrupalContext implements SnippetAcceptingContext {

  // Store entities as EntityMetadataWrappers for easy property inspection.
  protected $entities = array();

  protected $entity_type = '';
  protected $bundle = '';
  protected $bundle_key = FALSE;
  protected $field_map = array();
  protected $field_properties = array();

  /**
   * @var \Drupal\DKANExtension\Context\PageContext
   */
  protected $pageContext;
  /**
   * @var \Drupal\DKANExtension\Context\SearchAPIContext
   */
  protected $searchContext;


  public function __construct($field_map_overrides = array(), $bundle = '', $entity_type = 'node') {
    $entity_info = entity_get_info($entity_type);
    $this->entity_type = $entity_type;

    // Check that the bundle specified actually exists, or if none given,
    // that this is an entity with no bundles (single bundle w/ name of entity)
    $entity_bundles = array_keys($entity_info['bundles']);
    if (!in_array($bundle, $entity_bundles) && !in_array($this->entity_type, $entity_bundles)) {
      throw new \Exception("Bundle $bundle doesn't exist for entity type $this->entity_type.");
    }
    // Handle entities without bundles and identify the bundle key name (i.e. 'type')
    if ($bundle == '' && in_array($this->entity_type, $entity_info['bundles'])) {
      $this->bundle = $this->entity_type;
      $this->bundle_key = FALSE;
    }
    else {
      $this->bundle = $bundle;
      $this->bundle_key = $entity_info['entity_keys']['bundle'];
    }

    // Store the field properties for later.
    $property_info = entity_get_property_info($this->entity_type);
    $this->field_properties = $property_info[$this->bundle];

    // Collect the default and overridden field mappings.
    foreach ($this->field_properties as $field => $info) {
      // First check if this field mapping is overridden.
      if ($label = array_search($field, $field_map_overrides)) {
        $this->field_map[$label] = $field;
      }
      // Use the default label from field_properties;
      else {
        $this->field_map[$info['label']] = $field;
      }
    }
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
   * Helper function to create an entity as an EntityMetadataWrapper.
   *
   * Takes a array of key-mapped values and creates a fresh entity
   * using the data provided. The array should correspond to the context's field_map
   *
   * @param $entity - the array of values to create an entity from
   * @return the stdClass entity, or FALSE if failed
   */
  public function create($entity) {
    $entity = array();
    if ($this->bundle_key) {
      $entity[$this->bundle_key] = $this->bundle;
    }
    $entity = entity_create($this->entity_type, $entity);
    $wrapper = entity_metadata_wrapper($this->entity_type, $entity);

    return $wrapper;
  }


  public function set_field($wrapper, $label, $value) {

    // Make sure there is a mapping to an actual property.
    if (!isset($this->field_map[$label])) {
      throw new \Exeception("There is no field mapped to label '$label''.");
    }
    $property = $this->field_map[$label];

    $field_type = $this->field_properties[$this->field_map["label"]]['type'];

    switch ($field_type) {
      // Can be NID
      case 'integer':
        break;

      // Do our best to handle 0, false, "false", or "No"
      case 'boolean':
        if (gettype($value) == 'string') {
          $value = strtolower($value);
          $value = ($value == 'yes') ? true : $value;
          $value = ($value == 'no') ? false : $value;
        }
        $wrapper->$property = (bool) $value;
        break;

      // Dates - handle strings as best we can. See http://php.net/manual/en/datetime.formats.relative.php
      case 'date':
        $date = date_create($value);
        if ($date === false) {
          throw new \Exception("Couldn't create a date with '$value'");
        }
        break;

      // User reference
      case 'user':
        $user = user_load_by_name($value);
        if ($user === false) {
          throw new \Exception("Can't find a user with username '$value'");
        }
        $wrapper->$property = $user;
        break;

      // Text field formatting?
      case 'token':
        break;

      // Node reference.
      case 'node':
        break;

      // Simple text field.
      case 'text':
        break;

      // Formatted text like body
      case 'text_formatted':
        break;

      // Not sure (something more complex)
      case 'struct':
        break;

      // Images
      case 'field_item_image':
        break;

      // Links
      case 'field_item_link':
        break;

      // References to nodes
      case 'list<node>':
        break;
    }
    // If entity has an author, substitute in their uid
    if(isset($entity->author)) {
      $author = user_load_by_name($entity->author);
      $entity->uid = $author->uid;
    }
    else {
      // if not, then just assign it to user 1.
      $entity->uid = 1;
    }
    // Convert the string status from table into a flip bit
    if($this->entity_type === 'node' && isset($entity->status)){
      $entity->status = $entity->status === "Yes" ? 1 : 0;
    }


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
    foreach($this->arrayFromTableNode($entityTable) as $entity) {
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
   * Converts a TableNode into an array.
   *
   * Takes an TableNode and builds a multi-dimensional array,
   *
   * @param TableNode
   * @throws \Exception
   * @returns array()
   */
  function arrayFromTableNode(TableNode $itemsTable) {
    $items = array();
    foreach ($itemsTable as $itemHash) {
      $items[] = $itemHash;
    }
    return $items;
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
