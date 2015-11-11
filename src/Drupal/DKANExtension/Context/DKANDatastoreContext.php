<?php


namespace Drupal\DKANExtension\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;

class DKANDatastoreContext extends RawDrupalContext{

  /**
   * Create an empty DKAN Datastore object for a resource
   *
   * @param node - the resource node object
   * @return the datastore object
   */
  public function addDatastore($node){
    $uuid = $node->uuid;
    $datastore = dkan_datastore_go($uuid, NULL);
    return $datastore;
  }

  public function addFileToResource(){

  }

}