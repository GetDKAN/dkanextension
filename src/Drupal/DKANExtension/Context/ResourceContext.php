<?php

namespace Drupal\DKANExtension\Context;


use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;

/**
 * Defines application features from the specific context.
 */
class ResourceContext extends RawDKANEntityContext{

    use ModeratorTrait;

    public function __construct() {
        parent::__construct(
            'node',
            'resource',
            array('publisher' => 'og_group_ref', 'published' => 'status'),
            array('moderation', 'moderation_date')
        );
    }

    /**
     * Creates resources from a table.
     *
     * @Given resources:
     */
    public function addResources(TableNode $resourcesTable){
        parent::addMultipleFromTable($resourcesTable);
    }

  /**
   * @Given :provider previews are :setting for :format_name resources
   *
   * Changes variables in the database to enable or disable external previews
   */
  public function externalPreviewsAreEnabledForFormat($provider, $setting, $format_name)
  {
    $format = current(taxonomy_get_term_by_name($format_name, 'format'));
    $preview_settings = variable_get("dkan_dataset_format_previews_tid{$format->tid}", array());
    // If $setting was "enabled," the preview is turned on. Otherwise, it's
    // turned off.
    $preview_settings[$provider] = ($setting == 'enabled') ? $provider : 0;
    variable_set("dkan_dataset_format_previews_tid{$format->tid}", $preview_settings);
  }

    /**
     * Override RawDKANEntityContext::post_save()
     */
    public function post_save($wrapper, $fields) {
        parent::post_save($wrapper, $fields);
        $this->moderate($wrapper, $fields);
    }

  /**
   * @Then I should see a :previewtype preview
   */
  public function iShouldSeeAPreview($previewtype)
  {
    // XPATH for particualar preview type
    $previewtype_paths = array(
      'recline' => '//div[@class="recline-data-explorer"]',
      'zip' => '//div[@id="recline-zip-list"]',
      'image' => '//div[@id="recline-image-preview"]',
      'xm' => '//div[@id="recline-xml-preview"]',
      'json' => '//div[@id="recline-data-json"]',
      'geojson' => '//div[@id="map"]',
      // @todo: Add wms and arcgis tests
    );
    $preview = $page->find('xpath', $previewtype_paths[$previewtype]);
    if ($preview === NULL) {
      throw new \InvalidArgumentException(sprintf('Recline preview not found on page.'));
    }
  }
}
