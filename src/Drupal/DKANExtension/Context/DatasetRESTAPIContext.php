<?php
namespace Drupal\DKANExtension\Context;

use Drupal\DKANExtension\Context\RawDKANContext;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use \stdClass;

/**
 * Defines application features from the specific context.
 */
class DatasetRESTAPIContext extends RawDKANContext
{
  private $base_url = '';
  private $endpoint = '/api/dataset';
  private $entity = 'node';

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
      $response = $this->dkan_dataset_services_create_node($processed_data, $this->csrf_token, $this->cookie_session, $this->base_url, $this->endpoint, $this->entity);
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
        $this->dkan_dataset_services_update_node($processed_data, $node->getIdentifier(), $this->csrf_token, $this->cookie_session, $this->base_url, $this->endpoint, $this->entity);
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
      $this->dkan_dataset_services_delete_node($node->getIdentifier(), $this->csrf_token, $this->cookie_session, $this->base_url, $this->endpoint, $this->entity);
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
      $this->dkan_dataset_services_attach_file($file_data, $resource_id, $this->csrf_token, $this->cookie_session, $this->base_url, $this->endpoint, $this->entity);
    } else {
      throw new Exception(sprintf('The resource could not be found.'));
    }

    return true;
  }

  /**
   * Initiates curl request.
   */
  private function dkan_dataset_services_curl_init($request_url, $csrf_token = FALSE) {
    // cURL.
    $curl = curl_init($request_url);
    if ($csrf_token) {
      curl_setopt($curl, CURLOPT_HTTPHEADER, array('Accept: application/json', 'X-CSRF-Token: ' . $csrf_token));
    }
    else {
      // Accept JSON response.
      curl_setopt($curl, CURLOPT_HTTPHEADER, array('Accept: application/json'));
    }
    // Ask to not return Header.
    curl_setopt($curl, CURLOPT_HEADER, FALSE);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_FAILONERROR, TRUE);
    return $curl;
  }

  /**
   * Initiates curl request.
   */
  private function dkan_dataset_services_curl_parse($curl) {
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if ($http_code == 200) {
      $response = json_decode($response);
    }
    else {
      $http_message = curl_error($curl);
      die($http_message);
    }
    return $response;
  }

  /**
   * Logs in user.
   */
  private function dkan_dataset_services_user_login($base_url, $endpoint) {
    // Build request URL.
    $request_url = $base_url . $endpoint . '/user/login';
    // User data.
    $user_data = array(
      'username' => 'admin',
      'password' => 'admin',
    );
    $user_data = http_build_query($user_data);

    $curl = $this->dkan_dataset_services_curl_init($request_url);
    // Do a regular HTTP POST.
    curl_setopt($curl, CURLOPT_POST, 1);
    // Set POST data.
    curl_setopt($curl, CURLOPT_POSTFIELDS, $user_data);

    $logged_user = $this->dkan_dataset_services_curl_parse($curl);

    // Define cookie session.
    $cookie_session = $logged_user->session_name . '=' . $logged_user->sessid;
    return array('cookie_session' => $cookie_session, 'curl' => $curl);
  }

  /**
   * Retrives CSRF token.
   */
  private function dkan_dataset_services_get_csrf($cookie_session, $curl, $base_url) {
    // GET CSRF TOKEN.
    curl_setopt_array($curl, array(
      CURLOPT_RETURNTRANSFER => 1,
      CURLOPT_URL => $base_url . '/services/session/token',
    ));
    curl_setopt($curl, CURLOPT_COOKIE, "$cookie_session");

    $ret = new stdClass();

    $ret->response = curl_exec($curl);
    $ret->error    = curl_error($curl);
    $ret->info     = curl_getinfo($curl);

    $csrf_token = $ret->response;
    return $csrf_token;
  }

  /**
   * Server REST - node.create.
   */
  private function dkan_dataset_services_create_node($node_data, $csrf_token, $cookie_session, $base_url, $endpoint, $entity) {
    // REST Server URL.
    $request_url = $base_url . $endpoint . '/' . $entity;

    $node_data = http_build_query($node_data);

    $curl = $this->dkan_dataset_services_curl_init($request_url, $csrf_token);
    // Do a regular HTTP POST.
    curl_setopt($curl, CURLOPT_POST, 1);
    // Set POST data.
    curl_setopt($curl, CURLOPT_POSTFIELDS, $node_data);
    // Use the previously saved session.
    curl_setopt($curl, CURLOPT_COOKIE, "$cookie_session");

    $node = $this->dkan_dataset_services_curl_parse($curl);

    return $node;
  }

  /**
   * Server REST - node.create.
   */
  private function dkan_dataset_services_delete_node($nid, $csrf_token, $cookie_session, $base_url, $endpoint, $entity) {
    // REST Server URL.
    $request_url = $base_url . $endpoint . '/' . $entity . '/' . $nid;

    $curl = $this->dkan_dataset_services_curl_init($request_url, $csrf_token);
    // Set POST data.
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
    // Use the previously saved session.
    curl_setopt($curl, CURLOPT_COOKIE, "$cookie_session");

    $response = $this->dkan_dataset_services_curl_parse($curl);

    return $response;
  }

  /**
   * Server REST - node.update.
   */
  private function dkan_dataset_services_update_node($node_data, $nid, $csrf_token, $cookie_session, $base_url, $endpoint, $entity) {
    // REST Server URL.
    $request_url = $base_url . $endpoint . '/' . $entity . '/' . $nid;

    $node_data = http_build_query($node_data);

    $curl = $this->dkan_dataset_services_curl_init($request_url, $csrf_token);
    // Do a regular HTTP POST.
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
    // Set POST data.
    curl_setopt($curl, CURLOPT_POSTFIELDS, $node_data);
    // Use the previously saved session.
    curl_setopt($curl, CURLOPT_COOKIE, "$cookie_session");

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    $node = $this->dkan_dataset_services_curl_parse($curl);

    return $node;
  }

  /**
   * Server REST - node.attach_file.
   */
  private function dkan_dataset_services_attach_file($file_data, $node_id, $csrf_token, $cookie_session, $base_url, $endpoint, $entity) {
    // REST Server URL.
    $request_url = $base_url . $endpoint . '/' . $entity . '/' . $node_id . '/attach_file';

    $curl = $this->dkan_dataset_services_curl_init($request_url, $csrf_token);
    // Add 'Content-Type: multipart/form-data' on header.
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: multipart/form-data','Accept: application/json', 'X-CSRF-Token: ' . $csrf_token));
    // Do a regular HTTP POST.
    curl_setopt($curl, CURLOPT_POST, 1);
    // Set POST data.
    curl_setopt($curl, CURLOPT_POSTFIELDS, $file_data);
    // Use the previously saved session.
    curl_setopt($curl, CURLOPT_COOKIE, "$cookie_session");

    $node = $this->dkan_dataset_services_curl_parse($curl);

    return $node;
  }

  /**
   *
   */
  private function get_session_and_token() {
    // Get cookie_session and csrf_token.
    $user_login = $this->dkan_dataset_services_user_login($this->base_url, $this->endpoint);
    $this->cookie_session = $user_login['cookie_session'];
    $this->csrf_token = $this->dkan_dataset_services_get_csrf($this->cookie_session, $user_login['curl'], $this->base_url);
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