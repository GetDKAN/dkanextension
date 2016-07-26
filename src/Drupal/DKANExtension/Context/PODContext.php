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
    $url = $this->getMinkParameter('base_url') ? $this->getMinkParameter('base_url') : "http://127.0.0.1::8888";
    $results = open_data_schema_pod_process_validate($url . '/data.json', TRUE);
    if ($results['errors']) {
      throw new \Exception(sprintf('Data.json is not valid.'));
    }
  }
}
