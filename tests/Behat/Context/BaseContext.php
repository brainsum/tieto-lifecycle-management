<?php

namespace Brainsum\tieto_lifecycle_management\Tests\Behat\Context;

use Behat\Behat\Hook\Scope\AfterFeatureScope;
use Behat\Behat\Hook\Scope\BeforeFeatureScope;
use Behat\Behat\Tester\Exception\PendingException;
use Brainsum\DrupalBehatTesting\Traits\ModerationStateTrait;
use Brainsum\DrupalBehatTesting\Traits\PreviousNodeTrait;
use Brainsum\DrupalBehatTesting\Traits\ScheduledUpdateTrait;
use Brainsum\DrupalBehatTesting\Traits\TaxonomyTermTrait;
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
   * Config name for this module.
   */
  protected const MODULE_CONFIG = 'tieto_lifecycle_management.settings';

  /**
   * Navigate to the edit page of a previous node.
   */
  private function visitPreviousNodeEditPage(): void {
    $this->visitPath("/node/{$this->previousNode()->id()}/edit");
  }

  /**
   * Restore a backup of the default config.
   *
   * @param \Behat\Behat\Hook\Scope\BeforeFeatureScope $scope
   *   Behat scope.
   *
   * @BeforeFeature
   */
  public static function backupConfigBeforeFeature(BeforeFeatureScope $scope): void {
    (new static())->doBackupConfig();
  }

  /**
   * Restore a backup of the default config.
   *
   * @param \Behat\Behat\Hook\Scope\AfterFeatureScope $scope
   *   Behat scope.
   *
   * @AfterFeature
   */
  public static function restoreConfigAfterFeature(AfterFeatureScope $scope): void {
    (new static())->doRestoreConfig();
  }

  /**
   * Disables the life-cycle management globally.
   *
   * @Given life-cycle management has been globally disabled
   */
  public function lifeCycleManagementHasBeenGloballyDisabled(): void {
    $lifeCycleConfig = Drupal::configFactory()
      ->getEditable('tieto_lifecycle_management.settings');
    $lifeCycleConfig->set('disabled', TRUE);
    $lifeCycleConfig->save();
    Drupal::configFactory()->clearStaticCache();
  }

  /**
   * Disables the life-cycle management individually.
   *
   * @Given life-cycle management has been individually disabled
   */
  public function lifeCycleManagementHasBeenIndividuallyDisabled(): void {
    $lifeCycleConfig = Drupal::configFactory()
      ->getEditable('tieto_lifecycle_management.settings');

    $lfcActions = $lifeCycleConfig->get('actions');

    foreach ($lfcActions as $type => $bundles) {
      foreach ($bundles as $bundle => $actions) {
        foreach ($actions as $action => $settings) {
          $lfcActions[$type][$bundle][$action]['enabled'] = FALSE;
        }
      }
    }

    $lifeCycleConfig->set('actions', $lfcActions);

    $lfcFields = $lifeCycleConfig->get('fields');

    foreach ($lfcFields as $type => $bundles) {
      foreach ($bundles as $bundle => $fields) {
        foreach ($fields as $field => $settings) {
          $lfcFields[$type][$bundle][$field]['enabled'] = FALSE;
        }
      }
    }

    $lifeCycleConfig->set('fields', $lfcFields);

    $lifeCycleConfig->save(TRUE);
    Drupal::configFactory()->clearStaticCache();
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
   * Asserts that the moderation state is the given one for the content.
   *
   * @Then the moderation state of the content should change to :targetModerationState
   * @Then the moderation state of the content should stay :targetModerationState
   */
  public function moderationStateIsTheGivenOne(string $moderationState): void {
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
   * Asserts that the content has not been deleted.
   *
   * @Then the content should not be deleted
   */
  public function theContentShouldNotBeDeleted(): void {
    Assert::assertFalse($this->previousNodeWasDeleted());
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
      ->pageTextNotContains($this->parseModerationMessage($messageType, $message));
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
   *   The parsed message (either a fragment, or the full one).
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

    if ($timestamp === NULL) {
      return str_replace("@{$messageType}Date", '', $message);
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

  /**
   * Do the backup.
   */
  protected function doBackupConfig(): void {
    Drupal::state()->set(
      'behat_testing.config_backup.' . static::MODULE_CONFIG,
      Drupal::configFactory()->get(static::MODULE_CONFIG)->getRawData()
    );
  }

  /**
   * Do the restore.
   */
  protected function doRestoreConfig(): void {
    $savedConfig = Drupal::state()
      ->get('behat_testing.config_backup.' . static::MODULE_CONFIG, []);

    if (!empty($savedConfig)) {
      $currentConfig = Drupal::configFactory()->getEditable(static::MODULE_CONFIG);
      $currentConfig->setData($savedConfig);
      $currentConfig->save();

      Drupal::state()->delete('behat_testing.config_backup.' . static::MODULE_CONFIG);
    }
    Drupal::configFactory()->clearStaticCache();
  }

}
