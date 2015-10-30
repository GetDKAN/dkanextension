<?php

namespace Drupal\DKANExtension\Context;

use Behat\Gherkin\Node\TableNode;

class DataDashboardContext extends RawDKANEntityContext{

  public function __construct(){
    parent::__construct(array(
      'title' => 'title',
    ),
      'data_dashboard',
      'node'
    );
  }

  /**
   * @Given data dashboards:
   */
  public function addDataDashboard(TableNode $dashboardtable){
    parent::addMultipleFromTable($dashboardtable);
  }
}