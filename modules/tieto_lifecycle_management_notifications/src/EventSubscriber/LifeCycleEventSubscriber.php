<?php

namespace Drupal\tieto_lifecycle_management_notifications\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\tieto_lifecycle_management\Event\LifeCycleIgnoreEventInterface;
use Drupal\tieto_lifecycle_management\Event\LifeCycleUpdateEventInterface;
use Drupal\tieto_lifecycle_management\Service\EntityTime;
use Drupal\tieto_lifecycle_management\Service\ModerationHelper;
use Drupal\tieto_lifecycle_management_notifications\Data\EntityLifeCycleData;
use Drupal\tieto_lifecycle_management_notifications\Service\Mailer;
use Drupal\tieto_lifecycle_management_notifications\Service\NotificationStorage;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use function in_array;

/**
 * Class LifeCycleEventSubscriber.
 *
 * @package Drupal\tieto_lifecycle_management_notifications\EventSubscriber
 *
 * @todo: Collect all pages with the same publisher and withdrawal date on one
 *   email if possible.
 */
final class LifeCycleEventSubscriber implements EventSubscriberInterface {

  private $mailer;

  private $notificationStorage;

  private $moderationHelper;

  private $entityTime;

  private $time;

  private $dateFormatter;

  private $notificationConfig;

  /**
   * User storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  private $userStorage;

  /**
   * LifeCycleEventSubscriber constructor.
   *
   * @param \Drupal\tieto_lifecycle_management_notifications\Service\Mailer $mailer
   *   Custom mailer.
   * @param \Drupal\tieto_lifecycle_management_notifications\Service\NotificationStorage $notificationStorage
   *   Notification storage.
   * @param \Drupal\tieto_lifecycle_management\Service\ModerationHelper $moderationHelper
   *   Moderation helper service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   Date formatter service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\tieto_lifecycle_management\Service\EntityTime $entityTime
   *   Entity time service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    Mailer $mailer,
    NotificationStorage $notificationStorage,
    ModerationHelper $moderationHelper,
    TimeInterface $time,
    DateFormatterInterface $dateFormatter,
    ConfigFactoryInterface $configFactory,
    EntityTypeManagerInterface $entityTypeManager,
    EntityTime $entityTime
  ) {
    $this->mailer = $mailer;
    $this->notificationStorage = $notificationStorage;
    $this->moderationHelper = $moderationHelper;
    $this->entityTime = $entityTime;
    $this->time = $time;
    $this->dateFormatter = $dateFormatter;
    $this->notificationConfig = $configFactory->get('tieto_lifecycle_management_notifications.settings');
    $this->userStorage = $entityTypeManager->getStorage('user');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];

    $events[LifeCycleIgnoreEventInterface::NAME][] = ['onIgnore', 100];
    // @todo: $events[LifeCycleRemoveEventInterface::NAME][] = ['onRemove'];
    $events[LifeCycleUpdateEventInterface::NAME][] = ['onUpdate', 100];

    return $events;
  }

  /**
   * Event handler.
   *
   * @param \Drupal\tieto_lifecycle_management\Event\LifeCycleIgnoreEventInterface $event
   *   The event.
   *
   * @throws \Exception
   */
  public function onIgnore(LifeCycleIgnoreEventInterface $event): void {
    if (
      ($isDisabled = $this->notificationConfig->get('disabled'))
      && $isDisabled === TRUE
    ) {
      return;
    }

    $entityData = $this->entityToData($event->entity());

    if ($notificationId = $this->determineNotificationId($entityData)) {
      $users = $this->getRecipientUsers($event->entity(), $notificationId);

      foreach ($users as $user) {
        $this->mailer->sendReminder($user, [$this->entityToData($event->entity())], $notificationId);
      }
    }
  }

  /**
   * Event handler.
   *
   * @param \Drupal\tieto_lifecycle_management\Event\LifeCycleUpdateEventInterface $event
   *   The event.
   *
   * @throws \Exception
   */
  public function onUpdate(LifeCycleUpdateEventInterface $event): void {
    if (
      ($isDisabled = $this->notificationConfig->get('disabled'))
      && $isDisabled === TRUE
    ) {
      return;
    }

    if ($event->targetState() === 'unpublished_content') {
      $entityData = $this->entityToData($event->entity());

      // @todo: Make configurable.
      $notificationId = 'notification.content_got_unpublished';
      $users = $this->getRecipientUsers($event->entity(), $notificationId);

      foreach ($users as $user) {
        $this->mailer->sendNotification($user, [$entityData], $notificationId);
      }
    }
  }

  /**
   * Return entity data to be used in life-cycle emails.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\tieto_lifecycle_management_notifications\Data\EntityLifeCycleData
   *   The entity data.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   *
   * @todo: Move this out into a service.
   * @todo: Maybe populate additional metadata, e.g notification_target_users,
   *   notification_id, etc.
   */
  private function entityToData(EntityInterface $entity): EntityLifeCycleData {
    try {
      $unpublishTime = $this->entityTime->unpublishTime($entity);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $unpublishTime = NULL;
    }

    try {
      $deleteTime = $this->entityTime->deleteTime($entity);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $deleteTime = NULL;
    }

    $moderationState = NULL;
    if (
      $entity instanceof FieldableEntityInterface
      && $entity->hasField('moderation_state')
      && ($stateField = $entity->get('moderation_state'))
    ) {
      $moderationState = $stateField->target_id ?? NULL;
    }

    return new EntityLifeCycleData([
      'id' => $entity->id(),
      'title' => $entity->label(),
      'type' => $entity->getEntityTypeId(),
      'bundle' => $entity->bundle(),
      'url' => $entity->toUrl('canonical', ['absolute' => TRUE])
        ->toString(TRUE)
        ->getGeneratedUrl(),
      'moderationState' => $moderationState,
      'unpublishDate' => $this->formatTimestamp($unpublishTime),
      'unpublishTime' => $unpublishTime,
      'deleteDate' => $this->formatTimestamp($deleteTime),
      'deleteTime' => $deleteTime,
    ]);
  }

  /**
   * Format a timestamp.
   *
   * @param int|null $timestamp
   *   The timestamp or NULL.
   * @param string $format
   *   (Optional) format string.
   *
   * @return string|null
   *   The formatted timestamp, or NULL.
   */
  private function formatTimestamp(?int $timestamp, string $format = 'tieto_date'): ?string {
    if ($timestamp === NULL) {
      return NULL;
    }

    return $this->dateFormatter->format($timestamp, $format);
  }

  /**
   * Determine the notification ID for the entity.
   *
   * @param \Drupal\tieto_lifecycle_management_notifications\Data\EntityLifeCycleData $entityData
   *   The entity data.
   *
   * @return string|null
   *   The notification ID or NULL if it can't be determined.
   *
   * @todo: Move this out into a service.
   */
  private function determineNotificationId(EntityLifeCycleData $entityData): ?string {
    if (
      $entityData->unpublishTime === NULL
      || $this->moderationHelper->isCorrectTransition($entityData->moderationState, 'unpublished_content') === FALSE
    ) {
      return NULL;
    }

    $requestTime = $this->time->getRequestTime();

    // @todo: Load from config.
    // @todo: Add weight field.
    $notifications = [
      ['offset' => '14 days', 'id' => 'reminder.unpublished_content.half_month_before'],
      ['offset' => '1 month', 'id' => 'reminder.unpublished_content.one_month_before'],
    ];

    foreach ($notifications as $notification) {
      if ($this->entityTime->subtractOffset($entityData->unpublishTime, $notification['offset']) <= $requestTime) {
        return $notification['id'];
      }
    }

    return NULL;
  }

  /**
   * Return not yet notified users for an entity and notification ID.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $notificationId
   *   The notification ID.
   *
   * @return \Drupal\user\UserInterface[]
   *   The user array.
   *
   * @todo: Move this out into a service.
   */
  private function getRecipientUsers(EntityInterface $entity, string $notificationId): array {
    $notificationData = $this->notificationStorage->entityData($entity);

    $notifiedUsers = [];

    // Don't notify already notified users.
    if (!empty($notificationData[$notificationId])) {
      /** @var int[] $notifiedUsers */
      $notifiedUsers = $notificationData[$notificationId];
    }

    $users = $this->getEntityUsers($entity);
    $users = array_filter($users, static function (UserInterface $user) use ($notifiedUsers) {
      $alreadyNotified = in_array($user->id(), $notifiedUsers, FALSE);
      $noMail = ($user->getEmail() === NULL);
      return !($alreadyNotified || $noMail || $user->isBlocked());
    });

    if (empty($users)) {
      $users = $this->loadFallbackUsers();
    }

    return $users;
  }

  /**
   * Load fallback users.
   *
   * @return \Drupal\user\UserInterface[]
   *   The users.
   *
   * @todo: Move this out into a service.
   */
  private function loadFallbackUsers(): array {
    /** @var string[] $fallbackRecipients */
    $fallbackRecipients = $this->notificationConfig->get('fallback_recipients') ?? [];

    if (empty($fallbackRecipients)) {
      return [];
    }

    /** @var \Drupal\user\UserInterface[] $fallbackUsers */
    $fallbackUsers = array_values($this->userStorage->loadByProperties(['mail' => $fallbackRecipients]));
    return $fallbackUsers;
  }

  /**
   * Return users considered to be notified for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\user\UserInterface[]
   *   Array of users.
   *
   * @todo: Move this out into a service.
   */
  private function getEntityUsers(EntityInterface $entity): array {
    $users = [];

    if ($entity instanceof RevisionLogInterface) {
      // @todo: Maybe use $entity->getOwner()?
      $users[] = $entity->getRevisionUser();
    }

    if (
      $entity instanceof FieldableEntityInterface
      && $entity->hasField('field_information_owner')
    ) {
      /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $infoOwnerField */
      $infoOwnerField = $entity->get('field_information_owner');

      /** @var \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $item */
      foreach ($infoOwnerField as $item) {
        /** @var \Drupal\user\UserInterface|null $owner */
        if ($owner = $item->entity) {
          $users[] = $owner;
        }
      }
    }

    return $users;
  }

}
