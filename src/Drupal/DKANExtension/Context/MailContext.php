<?php
namespace Drupal\DKANExtension\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Behat\Behat\Context\SnippetAcceptingContext;

/**
 * Defines application features from the specific context.
 */
class MailContext extends RawDrupalContext implements SnippetAcceptingContext {

  public $originalMailSystem =  array('default-system' => 'DefaultMailSystem');
  protected $activeEmail;

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

  public function getMails() {
    // We can't use variable_get() because $conf is only fetched once per
    // scenario... (TODO IS THIS TRUE? seems like it should work fine because of variable_set()
    // setting the database and $conf (settings cache) --Frank)
    $variables = array_map('unserialize', db_query("SELECT name, value FROM {variable} WHERE name = 'drupal_test_email_collector'")->fetchAllKeyed());
    if (isset($variables['drupal_test_email_collector'])) {
      return $variables['drupal_test_email_collector'];
    }
    return array();
  }

  /**
   * @Then user :username should receive an email
   * @Then user :username should receive an email containing :content
   */
  public function userShouldReceiveAnEmail($username, $content = '')
  {
    $found = false;
    if($user = user_load_by_name($username)) {
      foreach ($this->getMails() as $message) {
        try {
          $this->assertShouldBeAddressedToEmail($message, $user->mail);
          $this->assertEmailShouldContain($message, $content);
          $found = TRUE;
        } catch (\Exception $e) {
        }
      }
      if (!$found) {
        throw new \Exception(sprintf("Email to %s with content %s not found.", $username, $content));
      }
    else {
        throw new \Exception(sprintf("User %s not found.", $username));
      }
    }
  }

  /**
   * @Then the email address :emailAddress should receive an email
   * @Then the email address :emailAddress should receive an email containing :content
   */
  public function emailShouldReceiveAnEmailContaining($emailAddress, $content = '')
  {
    $found = false;
    foreach ($this->getMails() as $message) {
      try {
        $this->assertShouldBeAddressedToEmail($message, $emailAddress);
        $this->assertEmailShouldContain($message, $content);
        $found = TRUE;
      } catch (\Exception $e) {
      }
    }
    if (!$found) {
      throw new \Exception(sprintf("Email to %s with content %s not found.", $emailAddress, $content));
    }
  }

  public function assertShouldBeAddressedToEmail($message, $email_addr) {
    if (!isset($message['to']) || $message['to'] !== $email_addr) {
      throw new \Exception(sprintf('Email to %s not found', $email_addr));
    }
  }

  public function assertEmailShouldContain($message, $content = '') {
      if (empty($content)) {
        return TRUE;
      }
      elseif (strpos($message['body'], $content) !== FALSE ||
        strpos($message['subject'], $content) !== FALSE) {
        return TRUE;
      }
      throw new \Exception('Did not find expected content in message body or subject.');
  }
}
