<?php
namespace Drupal\DrupalExtension\Context;

class DKANContext extends rawDKANContext
{
/**
   * @Given pages:
   */
  public function addPages(TableNode $pagesTable) {
    foreach ($pagesTable as $pageHash) {
      // @todo Add some validation.
      $this->addPage($pageHash);
    }
  }
  /**
   * @Given I am on (the) :page page
   */
  public function iAmOnPage($page_title)
  {
    if (isset($this->pages[$page_title])) {
      $session = $this->getSession();
      $url = $this->pages[$page_title]['url'];
      $session->visit($this->locatePath($url));
      $code = $session->getStatusCode();
      if ($code < 200 || $code >= 300) {
        throw new Exception("Page $page_title ($url) visited, but it returned a non-2XX response code of $code.");
      }
    }
    else {
      throw new Exception("Page $page_title not found in the pages array, was it added?");
    }
  }
  /**
   * @When I search for :term
   */
  public function iSearchFor($term) {
    $session = $this->getSession();
    $search_form_id = '#dkan-sitewide-dataset-search-form--2';
    $search_form = $session->getPage()->findAll('css', $search_form_id);
    if (count($search_form) == 1) {
      $search_form = array_pop($search_form);
      $search_form->fillField("search", $term);
      $search_form->pressButton("edit-submit--2");
      $results = $session->getPage()->find("css", ".view-dkan-datasets");
      if (!isset($results)) {
        throw new Exception("Search results region not found on the page.");
      }
    }
    else if(count($search_form) > 1) {
      throw new Exception("More than one search form found on the page.");
    }
    else if(count($search_form) < 1) {
      throw new Exception("No search form with the id of found on the page.");
    }
  }
  /**
   * @Then I should see a dataset called :text
   *
   * @throws \Exception
   *   If region or text within it cannot be found.
   */
  public function iShouldSeeADatasetCalled($text)
  {
    $session = $this->getSession();
    $page = $session->getPage();
    $search_region = $page->find('css', '.view-dkan-datasets');
    $search_results = $search_region->findAll('css', '.views-row');
    $found = false;
    foreach( $search_results as $search_result ) {
      $title = $search_result->find('css', 'h2');
      if ($title->getText() === $text) {
        $found = true;
      }
    }
    if (!$found) {
      throw new \Exception(sprintf("The text '%s' was not found", $text));
    }
  }
  /**
   * @Given groups:
   */
  public function addGroups(TableNode $groupsTable)
  {
    // Map readable field names to drupal field names.
    $field_map = array(
      'author' => 'author',
      'title' => 'title',
      'published' => 'published'
    );
    foreach ($groupsTable as $groupHash) {
      $node = new stdClass();
      $node->type = 'group';
      foreach($groupHash as $field => $value) {
        if(isset($field_map[$field])) {
          $drupal_field = $field_map[$field];
          $node->$drupal_field = $value;
        }
        else {
          throw new Exception(sprintf("Group field %s doesn't exist, or hasn't been mapped. See FeatureContext::addGroups for mappings.", $field));
        }
      }
      $created_node = $this->getDriver()->createNode($node);
      // Add the created node to the groups array.
      $this->groups[$created_node->nid] = $created_node;
      // Add the url to the page array for easy navigation.
      $this->addPage(array(
        'title' => $created_node->title,
        'url' => '/node/' . $created_node->nid
      ));
    }
  }
  /**
   * Creates multiple group memberships.
   *
   * Provide group membership data in the following format:
   *
   * | user  | group     | role on group        | membership status |
   * | Foo   | The Group | administrator member | Active            |
   *
   * @Given group memberships:
   */
  public function addGroupMemberships(TableNode $groupMembershipsTable)
  {
    foreach ($groupMembershipsTable->getHash() as $groupMembershipHash) {
      if (isset($groupMembershipHash['group']) && isset($groupMembershipHash['user'])) {
        $group = $this->getGroupByName($groupMembershipHash['group']);
        $user = user_load_by_name($groupMembershipHash['user']);
        // Add user to group with the proper group permissions and status
        if ($group && $user) {
          // Add the user to the group
          og_group("node", $group->nid, array(
            "entity type" => "user",
            "entity" => $user,
            "membership type" => OG_MEMBERSHIP_TYPE_DEFAULT,
            "state" => $this->getMembershipStatusByName($groupMembershipHash['membership status'])
          ));
          // Grant user roles
          $group_role = $this->getGroupRoleByName($groupMembershipHash['role on group']);
          og_role_grant("node", $group->nid, $user->uid, $group_role);
        } else {
          if (!$group) {
            throw new Exception(sprintf("No group was found with name %s.", $groupMembershipHash['group']));
          }
          if (!$user) {
            throw new Exception(sprintf("No user was found with name %s.", $groupMembershipHash['user']));
          }
        }
      } else {
        throw new Exception(sprintf("The group and user information is required."));
      }
    }
  }
  /**
   * @Given datasets:
   */
  public function addDatasets(TableNode $datasetsTable)
  {
    // Map readable field names to drupal field names.
    $field_map = array(
      'author' => 'author',
      'title' => 'title',
      'description' => 'body',
      'publisher' => 'og_group_ref',
      'published' => 'published',
      'resource format' => 'resource format',
      'tags' => 'field_tags',
    );
    foreach ($datasetsTable as $groupHash) {
      $node = new stdClass();
      $node->type = 'group';
      foreach($groupHash as $field => $value) {
        if(isset($field_map[$field])) {
          switch ($field) {
            case 'tags':
            case 'publisher':
              $value = $this->explode_list($value);
          }
          $drupal_field = $field_map[$field];
          $node->$drupal_field = $value;
        }
        else {
          throw new Exception(sprintf("Dataset's field %s doesn't exist, or hasn't been mapped. See FeatureContext::addDatasets for mappings.", $field));
        }
      }
      $created_node = $this->getDriver()->createNode($node);
      // Add the created node to the datasets array.
      $this->datasets[$created_node->nid] = $created_node;
      // Add the url to the page array for easy navigation.
      $this->addPage(array(
        'title' => $created_node->title,
        'url' => '/node/' . $created_node->nid
      ));
    }
  }

}
