<?php
namespace Drupal\DKANExtension\Context;


/**
 * Defines application features from the specific context.
 */
class PODContext extends RawDKANContext {
  /**
   * @When I should see valid data.json on the /data.json page
   */
  public function iShouldSeeValidDatasjonOnTheDatajsonPage() {
    $results = open_data_schema_pod_process_validate($this->getMinkParameter('base_url') . '/data.json', TRUE);
    if ($results['errors']) {
      throw new \Exception(sprintf('Data.json is not valid.'));
    }
  }
}
