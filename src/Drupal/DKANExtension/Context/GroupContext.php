<?php
namespace Drupal\DKANExtension\Context;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\TableNode;
use EntityFieldQuery;
use \stdClass;

/**
 * Defines application features from the specific context.
 */
class GroupContext extends RawDKANEntityContext {

  public function __construct() {
    parent::__construct(array(
      'author' => 'author',
      'title' => 'title',
      'published' => 'status'
    ),
      'group',
      'node'
    );
  }

  //public function deleteAll(AfterScenarioScope $scope){
//    foreach($this->entities as $entity){
//      $id = $entity->nid->value();
//      $query = new EntityFieldQuery();
//      $result = $query
//          ->entityCondition('entity_type', 'og_membership')
//          ->propertyCondition('gid', $id, '=')
//          ->execute();
//      if(!empty($result)) {
//        foreach (reset($result) as $membership) {
//          $ids[] = $membership->id;
//        }
//        _og_orphans_delete($ids);
//      }
//
//
//      $entity->delete();
//    }
  //}

  public function create($entity){
    $entity = parent::create($entity);
    $wrapper = entity_metadata_wrapper('node', $entity, array('bundle' => 'group'));
    return $wrapper;
  }

  /**
   * @Given groups:
   */
  public function addGroups(TableNode $groupsTable) {
    parent::addMultipleFromTable($groupsTable);
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
          og_group("node", $group->nid->value(), array(
            "entity type" => "user",
            "entity" => $user,
            "membership type" => OG_MEMBERSHIP_TYPE_DEFAULT,
            "state" => $this->getMembershipStatusByName($groupMembershipHash['membership status'])
          ));
          // Grant user roles
          $group_role = $this->getGroupRoleByName($groupMembershipHash['role on group']);
          og_role_grant("node", $group->nid->value(), $user->uid, $group_role);
        } else {
          if (!$group) {
            throw new \Exception(sprintf("No group was found with name %s.", $groupMembershipHash['group']));
          }
          if (!$user) {
            throw new \Exception(sprintf("No user was found with name %s.", $groupMembershipHash['user']));
          }
        }
      } else {
        throw new \Exception(sprintf("The group and user information is required."));
      }
    }
  }

  /**
   * @Given /^I am a "([^"]*)" of the group "([^"]*)"$/
   */
  public function iAmAMemberOfTheGroup($role, $group_name) {
    // Get group
    $group = $this->getGroupByName($group_name);

    $role = $this->getGroupRoleByName($role);

    if ($account = $this->getCurrentUser()) {
      og_group('node', $group->getIdentifier(), array(
          "entity type" => "user",
          "entity" => $account,
          "membership type" => OG_MEMBERSHIP_TYPE_DEFAULT,
      ));
      og_role_grant('node', $group->getIdentifier(), $account->uid, $role);
    }
    else {
      throw new \InvalidArgumentException(sprintf('Could not find current user'));
    }

  }

  /**
   * Get Group by name
   *
   * @param $name
   * @return EntityMetadataWrapper group or FALSE
   */
  public function getGroupByName($name) {
    foreach($this->entities as $group) {
      if ($group->title->value() == $name) {
        return $group;
      }
    }
    return FALSE;
  }
  /**
   * Get Group Role ID by name
   *
   * @param $name
   * @return stdClass Role ID or FALSE
   */
  private function getGroupRoleByName($name) {
    $group_roles = og_get_user_roles_name();
    return array_search($name, $group_roles);
  }
  /**
   * Get Membership Status Code by name
   *
   * @param $name
   * @return stdClass Membership status code or FALSE
   */
  private function getMembershipStatusByName($name) {
    switch($name) {
      case 'Active':
        return OG_STATE_ACTIVE;
        break;
      case 'Pending':
        return OG_STATE_PENDING;
        break;
      case 'Blocked':
        return OG_STATE_BLOCKED;
        break;
      default:
        break;
    }
    return FALSE;
  }

}
