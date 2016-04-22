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
    $page_content = $this->getSession()->getDriver()->getContent();
    var_dump($page_content);

    $results = open_data_schema_pod_process_validate($this->getMinkParameter('base_url') . '/data.json', TRUE);
    if ($results['errors']) {
      throw new \Exception(sprintf('Data.json is not valid.'));
    }
  }
}
