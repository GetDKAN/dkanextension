<?php
namespace Drupal\DKANExtension\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Behat\Behat\Context\SnippetAcceptingContext;

/**
 * Defines application features from the specific context.
 */
class MailContext extends RawDrupalContext implements SnippetAcceptingContext {

  public $originalMailSystem =  array('default-system' => 'DefaultMailSystem');

  public function __construct() {
    $this->originalMailSystem = variable_get('mail_system', $this->originalMailSystem);
  }

  /**
   * @BeforeScenario @mail
   */
  public function beforeMail() {
    // Setup the testing mail system.
    $this->setMailSystem(array('default-system' => 'TestingMailSystem'));
  }

  /**
   * @AfterScenario @mail
   */
  public function afterMail() {
    // Restore the original mail system.
    $this->setMailSystem($this->originalMailSystem);
  }

  public function setMailSystem($system) {
    variable_set('mail_system', $system);
    $this->flushMailSystem();
  }

  public function getMailSystem() {
    variable_get('mail_system', $this->originalMailSystem);
  }

  public function flushMailSystem() {
    variable_set('drupal_test_email_collector', array());
  }

  public function getMail() {
    variable_get('drupal_test_email_collector', array());
  }

  //TODO Create steps for testing values of sent mails.
}
