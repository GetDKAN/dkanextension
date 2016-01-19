<?php
namespace Drupal\DKANExtension\Context;

use Behat\Testwork\Environment\Environment;
use Drupal\DKANExtension\ServiceContainer\EntityStore;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Behat\Gherkin\Node\TableNode;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use EntityDrupalWrapper;
use EntityMetadataWrapperException;
use Symfony\Component\Config\Definition\Exception\Exception;
use Drupal\DKANExtension\Context\EntityAwareInterface;


/**
 * Defines application features from the specific context.
 */
class RawDKANContext extends RawDrupalContext implements EntityAwareInterface {

  /**
   * @var \Drupal\DKANExtension\Context\PageContext
   */
  protected $pageContext;
  /**
   * @var \Drupal\DKANExtension\Context\SearchAPIContext
   */
  protected $searchContext;
  /**
   * @var \Drupal\DKANExtension\ServiceContainer\EntityStore
   */
  protected $entityStore;

  public function setEntityStore(EntityStore $entityStore) {
    $this->entityStore = $entityStore;
  }
  public function getEntityStore() {
    return $this->entityStore;
  }

  /**
   * Check toolbar if this->user isn't working.
   */
  public function getCurrentUser() {
    if ($this->user) {
      return $this->user;
    }
    $session = $this->getSession();
    $page = $session->getPage();
    $xpath = $page->find('xpath', "//div[@class='content']/span[@class='links']/a[1]");
    $userName = $xpath->getText();
    $uid = db_query('SELECT uid FROM users WHERE name = :user_name', array(':user_name' =>  $userName))->fetchField();
    if ($uid && $user = user_load($uid)) {
      return $user;
    }
    return FALSE;
  }

  /**
   * @BeforeScenario
   */
  public function gatherContexts(BeforeScenarioScope $scope) {
    /** @var Environment $environment */
    $environment = $scope->getEnvironment();
    $this->pageContext = $environment->getContext('Drupal\DKANExtension\Context\PageContext');
    $this->searchContext = $environment->getContext('Drupal\DKANExtension\Context\SearchAPIContext');
  }

}
