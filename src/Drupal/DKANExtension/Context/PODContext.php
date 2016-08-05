<?php
namespace Drupal\DKANExtension\Context;

use Drupal\DKANExtension\Context\PageContext;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;


/**
 * Defines application features from the specific context.
 */
class PODContext extends RawDKANContext {

  /**
   * @BeforeScenario
   */
 public function gatherContexts(BeforeScenarioScope $scope){
   parent::gatherContexts($scope);
   $environment = $scope->getEnvironment();
   $this->pageContext = $environment->getContext('Drupal\DKANExtension\Context\PageContext');
 }

  /**
   * @When I should see valid data.json
   */
  public function iShouldSeeValidDatasjon() {
    // Change /data.json path to /json during tests. The '.' on the filename breaks tests on CircleCI's server.
    $data_json = open_data_schema_map_api_load('data_json_1_1');
    $data_json->endpoint = 'json';
    drupal_write_record('open_data_schema_map', $data_json, 'id');
    drupal_static_reset('open_data_schema_map_api_load_all');
    menu_rebuild();

    // Get base URL.
    $url = $this->getMinkParameter('base_url') ? $this->getMinkParameter('base_url') : "http://127.0.0.1::8888";
    
    // Validate POD.
    $results = open_data_schema_pod_process_validate($url . '/json', TRUE);
    if ($results['errors']) {
      throw new \Exception(sprintf('Data.json is not valid.'));
    }
  }
}
