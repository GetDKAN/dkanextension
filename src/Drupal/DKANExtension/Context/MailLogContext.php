<?php

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;

/**
 * Defines application features from the specific context.
 */
class MailLogContext extends RawDrupalContext implements SnippetAcceptingContext {

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
    $this->setMailSystem(array('default-system' => 'MaillogMailSystem'));
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
    db_truncate('maillog')->execute();
  }

  public function getMails() {
    $result = db_query("SELECT idmaillog, header_from, header_to, header_reply_to, header_all, subject, body FROM {maillog};");

    if ($result == FALSE) {
      $maillog = array();
    }else {
      $result = db_query("SELECT idmaillog, header_from, header_to, header_reply_to, subject, body FROM {maillog};")->fetchAllAssoc('idmaillog');
    }
    return $result;
  }

  /**
   * @Then (the) user :username should receive an email
   * @Then (the) user :username should receive an email containing :content
   */
  public function userShouldReceiveAnEmail($username, $content = '')
  {
    $found = false;
    if($user = user_load_by_name($username)) {
      foreach ($this->getMails() as $message) {
        try {
          $this->assertShouldBeAddressedToEmail($message, $user->mail);
          if ($content !== '') {
            $this->assertEmailShouldContain($message, $content);
          }
          $found = TRUE;
        } catch (\Exception $e) {
        }
      }
      if (!$found) {
        throw new \Exception(sprintf("Email to %s with content %s not found.", $username, $content));
      }
    }
    else {
      throw new \Exception(sprintf("User %s not found.", $username));
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

  /**
   *  @Then the email address :emailAddress should not receive an email
   */
  public function emailShouldNotReceiveAnEmail($emailAddress)
  {
    $found = false;
    foreach ($this->getMails() as $message) {
      try {
        $this->assertShouldBeAddressedToEmail($message, $emailAddress);
        $found = TRUE;
      } catch (\Exception $e) {
      }
    }
    if ($found) {
      throw new \Exception(sprintf("Email was sent to %s", $emailAddress));
    }
  }

  /**
   *  @Then (the) user :username should not receive an email
   */
  public function userShouldNotReceiveAnEmail($username)
  {
    $found = false;
    if($user = user_load_by_name($username)) {
      foreach ($this->getMails() as $message) {
        try {
          $this->assertShouldBeAddressedToEmail($message, $user->mail);
          $found = TRUE;
        } catch (\Exception $e) {
        }
      }
      if ($found) {
        throw new \Exception(sprintf("Email was sent to %s", $username));
      }
    }
    else {
      throw new \Exception(sprintf("User %s not found.", $username));
    }
  }

  public function assertShouldBeAddressedToEmail($message, $email_addr) {
    if (!isset($message->header_to) || $message->header_to !== $email_addr) {
      throw new \Exception(sprintf('Email to %s not found', $email_addr));
    } else {
    }
  }

  public function assertEmailShouldContain($message, $content = '') {
      if (empty($content)) {
        return TRUE;
      }
      elseif (strpos($message->body, $content) !== FALSE ||
        strpos($message->subject, $content) !== FALSE) {
        return TRUE;
      }
      throw new \Exception('Did not find expected content in message body or subject.');
  }

  /**
   * @Given the email queue is cleared
   *
   * This step is useful to clear the email queue between steps if needed.
   */
  public function theEmailQueueIsCleared()
  {
    $this->flushMailSystem();
  }

}
