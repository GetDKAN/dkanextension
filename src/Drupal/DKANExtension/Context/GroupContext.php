<?php
namespace Drupal\DKANExtension\Context;

use Behat\Gherkin\Node\TableNode;
use \stdClass;

/**
 * Defines application features from the specific context.
 */
class GroupContext extends RawDKANEntityContext {

  public function __construct() {
    parent::__construct(array(
      'author' => 'author',
      'title' => 'title',
      'published' => 'published'
    ),
      'group',
      'node'
    );
  }


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
   * Get Group by name
   *
   * @param $name
   * @return stdClass group or FALSE
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
