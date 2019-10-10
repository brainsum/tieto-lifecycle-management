<?php

namespace Brainsum\TietoModules\tieto_lifecycle_management\Tests\Behat\Context;

use Behat\Behat\Tester\Exception\PendingException;
use Brainsum\DrupalBehatTesting\Helper\ModerationStateTrait;
use Brainsum\DrupalBehatTesting\Helper\PreviousNodeTrait;
use Brainsum\DrupalBehatTesting\Helper\ScheduledUpdateTrait;
use Brainsum\DrupalBehatTesting\Helper\TaxonomyTermTrait;
use DateInterval;
use Drupal;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use PHPUnit\Framework\Assert;
use RuntimeException;

/**
 * Class BaseContext.
 *
 * @usage: Extend this in your project, implement the abstract methods.
 */
abstract class BaseContext extends RawDrupalContext {

  use ModerationStateTrait;
  use PreviousNodeTrait;
  use ScheduledUpdateTrait;
  use TaxonomyTermTrait;

  /**
   * Temporary timezone string.
   *
   * @todo: Fix setting the timezone; use the currentUser's.
   */
  protected const TIMEZONE = 'Europe/Budapest';

  /**
   * Navigate to the edit page of a previous node.
   */
  private function visitPreviousNodeEditPage(): void {
    $this->visitPath("/node/{$this->previousNode()->id()}/edit");
  }

  /**
   * Creates content, then redirects to the edit page.
   *
   * @Given I edit a(n) :moderationState content of type :contentType
   */
  public function editContent(
    string $moderationState,
    string $contentType
  ): void {
    $newNode = $this->generateProjectNode($contentType, $moderationState);
    $this->setPreviousNode($newNode);
    $this->visitPreviousNodeEditPage();
  }

  /**
   * Creates content, then redirects to the edit page.
   *
   * @Given I edit a manually not moderated :moderationState content of type :contentType
   */
  public function editUnmoderatedContent(
    string $moderationState,
    string $contentType
  ): void {
    $newNode = $this->generateProjectNode($contentType, $moderationState);
    $this->setPreviousNode($newNode);
    $this->visitPreviousNodeEditPage();
  }

  /**
   * Creates content.
   *
   * @Given a manually not moderated :moderationState :contentType, last published :time ago
   */
  public function givenPublishedUnmoderatedContent(
    string $moderationState,
    string $contentType,
    string $time
  ): void {
    $date = $this->timeAgoToDate($time);
    $timeAsTimestamp = $date->getTimestamp();

    if ($timeAsTimestamp === FALSE) {
      throw new RuntimeException("The time string '$time' could not be converted to a timestamp.");
    }

    // Create a published node.
    $newNode = $this->generateProjectNode(
      $contentType,
      'Published',
      [
        'created' => $timeAsTimestamp,
        'changed' => $timeAsTimestamp,
      ]
    );

    // Re-save the node with the desired state, if needed.
    $stateMachineName = $this->stateMachineName($moderationState);
    if ($stateMachineName !== 'published') {
      $newNode->set('moderation_state', $stateMachineName);
      $newNode->save();
    }

    $this->setPreviousNode($newNode);

    $this->visitPreviousNodeEditPage();
  }

  /**
   * Creates content, then redirects to the edit page.
   *
   * @Given I edit a manually moderated :moderationState content of type :contentType
   */
  public function editModeratedContent(
    string $moderationState,
    string $contentType
  ): void {
    $time = Drupal::time()->getRequestTime() + 3600;

    // Create a published node.
    $newNode = $this->generateProjectNode(
      $contentType,
      $moderationState
    );
    $newNode->set(
      $this->scheduleFieldName($moderationState),
      $this->generateScheduling($time, $moderationState, $newNode)
    );
    $newNode->save();
    $this->setPreviousNode($newNode);

    $this->visitPreviousNodeEditPage();
  }

  /**
   * Creates content.
   *
   * @Given a manually moderated :moderationState :contentType, last published :time ago
   */
  public function givenPublishedModeratedContent(
    string $moderationState,
    string $contentType,
    string $time
  ): void {
    $date = $this->timeAgoToDate($time);
    $timeAsTimestamp = $date->getTimestamp();

    if ($timeAsTimestamp === FALSE) {
      throw new RuntimeException("The time string '$time' could not be converted to a timestamp.");
    }

    $scheduleTime = Drupal::time()->getRequestTime() + 3600;
    $stateMachineName = $this->stateMachineName($moderationState);

    // Create a published node.
    $newNode = $this->generateProjectNode(
      $contentType,
      'Published',
      [
        'created' => $timeAsTimestamp,
        'changed' => $timeAsTimestamp,
      ]
    );

    // Re-save the node with the desired state, if needed.
    if ($stateMachineName !== 'published') {
      $newNode->set('moderation_state', $stateMachineName);
    }

    $newNode->set(
      $this->scheduleFieldName($moderationState),
      $this->generateScheduling($scheduleTime, $moderationState, $newNode)
    );
    $newNode->save();
    $this->setPreviousNode($newNode);
    $this->visitPreviousNodeEditPage();
  }

  /**
   * Creates content.
   *
   * @Given a manually moderated, never published :moderationState :contentType
   */
  public function givenUnpublishedModeratedContent(
    string $moderationState,
    string $contentType
  ): void {
    throw new PendingException();
  }

  /**
   * Creates content.
   *
   * @Given a manually not moderated, never published :moderationState :contentType, updated :time ago
   */
  public function givenUnpublishedUnmoderatedContent(
    string $moderationState,
    string $contentType,
    string $time
  ): void {
    $stateMachineName = $this->stateMachineName($moderationState);

    if ($stateMachineName === 'published') {
      throw new RuntimeException('Cannot set "published" state for the "never published" test case.');
    }

    $date = $this->timeAgoToDate($time);
    $timeAsTimestamp = $date->getTimestamp();

    if ($timeAsTimestamp === FALSE) {
      throw new RuntimeException("The time string '$time' could not be converted to a timestamp.");
    }

    // Create a published node.
    $newNode = $this->generateProjectNode(
      $contentType,
      $moderationState,
      [
        'created' => $timeAsTimestamp,
        'changed' => $timeAsTimestamp,
      ]
    );

    $this->setPreviousNode($newNode);
    $this->visitPreviousNodeEditPage();
  }

  /**
   * Asserts that the moderation state changed for the content.
   *
   * @Then the moderation state of the content should change to :targetModerationState
   */
  public function contentStateShouldChange(string $moderationState): void {
    $stateMachineName = $this->stateMachineName($moderationState);
    $this->reloadPreviousNode();
    Assert::assertEquals($stateMachineName,
      $this->previousNode()->get('moderation_state')->target_id);
  }

  /**
   * Asserts that the content has been deleted.
   *
   * @Then the content should be deleted
   */
  public function contentShouldBeDeleted(): void {
    Assert::assertTrue($this->previousNodeWasDeleted());
  }

  /**
   * Asserts that the moderation message exists.
   *
   * @Then I should see the :messageType moderation message :message
   */
  public function moderationMessageDetection(
    string $messageType,
    string $message
  ): void {
    $this->assertSession()
      ->pageTextContains($this->parseModerationMessage($messageType, $message));
  }

  /**
   * Asserts that the moderation message is missing.
   *
   * @Then I should not see the :messageType moderation message :message
   */
  public function missingModerationMessageDetection(
    string $messageType,
    string $message
  ): void {
    $this->assertSession()
      ->pageTextNotContains($this->parseModerationMessage($messageType,
        $message));
  }

  /**
   * Parse the moderation message.
   *
   * @param string $messageType
   *   Message type.
   * @param string $message
   *   Message.
   *
   * @return string
   *   The parsed message.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function parseModerationMessage(
    string $messageType,
    string $message
  ): string {
    // @todo: REFACTOR.
    // @todo: Maybe use targetState instead of messageType; might be more consistent.
    //
    // @see: \Drupal\DrupalExtension\Context\MinkContext::fixStepArgument().
    $parsedText = str_replace('\\"', '"', $message);

    /** @var \Drupal\tieto_lifecycle_management\Service\EntityTime $entityTime */
    $entityTime = Drupal::service('tieto_lifecycle_management.entity_time');

    $timestamp = NULL;
    switch ($messageType) {
      case 'delete':
        // @todo: Cleanup.
        $timestamp = $entityTime->lastPublishTime($this->previousNode()) === NULL
          ? $entityTime->unpublishedEntityDeleteTime($this->previousNode())
          : $entityTime->deleteTime($this->previousNode());
        break;

      case 'unpublish':
        $timestamp = $entityTime->unpublishTime($this->previousNode());
        break;

      case 'archive':
        $timestamp = $entityTime->archiveTime($this->previousNode());
        break;
    }

    // @todo: This will make some cases fail. Maybe, in this case try searching for a message regex without the date. The should be good enough.
    if ($timestamp === NULL) {
      throw new RuntimeException('Placeholder replacement for the message could not be determined.');
    }

    /** @var \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter */
    $dateFormatter = Drupal::service('date.formatter');
    // @todo: Set timezone in a non-shitty way.
    $formattedDate = $dateFormatter->format($timestamp, 'tieto_date', '',
      static::TIMEZONE);
    $placeholder = "@{$messageType}Date";
    return str_replace($placeholder, $formattedDate, $parsedText);
  }

  /**
   * Turn time string into DrupalDateTime.
   *
   * Note, the time string is considered "ago", this means it's subtracted from
   * the current time while constructing the date object.
   *
   * @param string $time
   *   The time string (e.g "1 month 1 minute").
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime
   *   The DateTime object.
   */
  protected function timeAgoToDate(string $time): DrupalDateTime {
    $date = new DrupalDateTime('now', static::TIMEZONE);
    $date->sub(DateInterval::createFromDateString($time));
    return $date;
  }

}
