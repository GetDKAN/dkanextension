<?php
namespace Drupal\DKANExtension\Context;

use Drupal\DKANExtension\Context\RawDKANContext;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use \stdClass;

require_once "Utils/dkan_rest_api_crud.php";

/**
 * Defines application features from the specific context.
 */
class DatasetRESTAPIContext extends RawDKANContext
{
  private $base_url = '';
  private $endpoint = '/api/dataset';
  private $entity = 'node';

  private $username = 'admin';
  private $password = 'admin';
  private $cookie_session = '';
  private $csrf_token = '';

  private $rest_api_fields_map = array(
    'resource' => array(
      'type' => 'type',
      'title' => 'title',
      'body' => 'body[und][0][value]',
      'status' => 'status'
    ),
    'dataset' => array(
      'type' => 'type',
      'title' => 'title',
      'body' => 'body[und][0][value]',
      'status' => 'status',
      'resource' => 'field_resources[und][0][target_id]'
    )
  );

  /**
   * @BeforeScenario
   */
  public function gatherContexts(BeforeScenarioScope $scope){
    parent::gatherContexts($scope);
    $environment = $scope->getEnvironment();
    $this->dkanContext = $environment->getContext('Drupal\DKANExtension\Context\DKANContext');
  }

  /**
   * @Given /^I use the Dataset REST API to create the nodes:$/
   */
  public function iUseTheDatasetRestApiToCreateTheNodes($data) {
    // Setup base URL.
    $this->base_url = $this->getMinkParameter('base_url');
    // Login and get token.
    $this->get_session_and_token();

    // Create nodes
    foreach ($data->getHash() as $node_data) {
      // Get node data.
      $processed_data = $this->build_node_data($node_data);
      // Create node.
      $response = Utils\dkan_rest_api_create_node($processed_data, $this->csrf_token, $this->cookie_session, $this->base_url, $this->endpoint, $this->entity);
      // Keep track of all created node.
      $node = node_load($response->nid);
      $wrapper = entity_metadata_wrapper('node', $node);
      $this->dkanContext->entityStore->store('node', $processed_data['type'], $node->nid, $wrapper, $wrapper->label());
    }
    return true;
  }

  /**
   * @Given I use the Dataset REST API to update the node :arg1 with:
   */
  public function iUseTheDatasetRestApiToUpdateTheNodeWith($node_title, $data) {
    // Setup base URL.
    $this->base_url = $this->getMinkParameter('base_url');
    // Get node.
    $node = $this->dkanContext->entityStore->retrieve_by_name($node_title);
    if ($node) {
      // Login and get token.
      $this->get_session_and_token();

      // Create nodes
      foreach ($data->getHash() as $node_data) {
        // Get node data.
        $processed_data = $this->build_node_data($node_data, $node);
        // Update node.
        Utils\dkan_rest_api_update_node($processed_data, $node->getIdentifier(), $this->csrf_token, $this->cookie_session, $this->base_url, $this->endpoint, $this->entity);
      }
    } else {
      throw new Exception(sprintf('The node could not be found.'));
    }
    return true;
  }

  /**
   * @Given I use the Dataset REST API to delete the node :arg1
   */
  public function iUseTheDatasetRestApiToDeleteTheNode($node_title) {
    // Setup base URL.
    $this->base_url = $this->getMinkParameter('base_url');
    // Get node.
    $node = $this->dkanContext->entityStore->retrieve_by_name($node_title);
    if ($node) {
      // Login and get token.
      $this->get_session_and_token();

      // Delete node.
      Utils\dkan_rest_api_delete_node($node->getIdentifier(), $this->csrf_token, $this->cookie_session, $this->base_url, $this->endpoint, $this->entity);
    } else {
      throw new Exception(sprintf('The node could not be found.'));
    }
    return true;
  }

  /**
   * @Given I use the Dataset REST API to attach the file :file to :resource
   */
  public function iUseTheDatasetRestApiToAttachTheFileTo($file, $resource_title) {
    // Setup base URL.
    $this->base_url = $this->getMinkParameter('base_url');
    // Get resource.
    $resource = $this->dkanContext->entityStore->retrieve_by_name($resource_title);
    if ($resource) {
      // Get resource ID.
      $resource_id = $resource->getIdentifier();
      // Prepare file data.
      $file_data = array(
        "files[1]" => curl_file_create($this->getMinkParameter('files_path') . $file),
        "field_name" => "field_upload",
        "attach" => 1
      );
      // Login and get token.
      $this->get_session_and_token();
      // Attach file.
      Utils\dkan_rest_api_attach_file($file_data, $resource_id, $this->csrf_token, $this->cookie_session, $this->base_url, $this->endpoint, $this->entity);
    } else {
      throw new Exception(sprintf('The resource could not be found.'));
    }

    return true;
  }

  /**
   *
   */
  private function get_session_and_token() {
    // Get cookie_session and csrf_token.
    $user_login = Utils\dkan_rest_api_user_login($this->base_url, $this->endpoint, $this->username, $this->password);
    $this->cookie_session = $user_login['cookie_session'];
    $this->csrf_token = Utils\dkan_rest_api_get_csrf($this->cookie_session, $user_login['curl'], $this->base_url);
  }

  /**
   *
   */
  private function build_node_data($data, $node = null) {
    $node_data = array();

    if (!$node && !isset($data['type'])) {
      throw new Exception(sprintf('The "type" column is required.'));
    }

    // Get node type.
    $node_type = ($node) ? $node->getBundle() : $data['type'];

    // Get the rest api field map for the content type.
    $rest_api_fields = $this->rest_api_fields_map[$node_type];
    foreach ($data as $field => $field_value) {
      if (isset($rest_api_fields[$field])) {
        $node_data[$rest_api_fields[$field]] = $this->process_field($field, $field_value);
      }
    }

    // If the node is being updated then the type of node should not be modified.
    if ($node && isset($node_data['type'])) {
      unset($node_data['type']);
    }

    return $node_data;
  }

  /**
   *
   */
  private function process_field($field, $field_value) {
    switch ($field) {
      case 'resource': {
        $resource = $this->dkanContext->entityStore->retrieve_by_name($field_value);
        if ($resource) {
          return $resource->entityKey('title') . ' (' . $resource->getIdentifier() . ')';
        }
        break;
      }
      default:
        return $field_value;
    }

    return false;
  }
}