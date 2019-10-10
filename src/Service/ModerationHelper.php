<?php

namespace Drupal\tieto_lifecycle_management\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tieto_lifecycle_management\Constant\RemovalReason;
use Drupal\tieto_lifecycle_management\Event\LifeCycleIgnoreEvent;
use Drupal\tieto_lifecycle_management\Event\LifeCycleRemoveEvent;
use Drupal\tieto_lifecycle_management\Event\LifeCycleUpdateEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use function array_chunk;
use function array_keys;
use function json_encode;

/**
 * Class ModerationHelper.
 *
 * @package Drupal\tieto_lifecycle_management\Service
 */
final class ModerationHelper {

  use MessengerTrait;

  private $entityTypeManager;

  private $time;

  private $lifeCycleConfig;

  private $logger;

  private $eventDispatcher;

  private $moderationMessage;

  private $entityTime;

  /**
   * ModerationHelper constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   Logger channel factory.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
   *   Event dispatcher.
   * @param \Drupal\tieto_lifecycle_management\Service\ModerationMessage $moderationMessage
   *   Moderation message service.
   * @param \Drupal\tieto_lifecycle_management\Service\EntityTime $entityTime
   *   Entity time service.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    TimeInterface $time,
    ConfigFactoryInterface $configFactory,
    LoggerChannelFactoryInterface $loggerChannelFactory,
    EventDispatcherInterface $dispatcher,
    ModerationMessage $moderationMessage,
    EntityTime $entityTime
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->time = $time;
    $this->lifeCycleConfig = $configFactory->get('tieto_lifecycle_management.settings');
    $this->logger = $loggerChannelFactory->get('tieto_lifecycle_management');
    $this->eventDispatcher = $dispatcher;
    $this->moderationMessage = $moderationMessage;
    $this->entityTime = $entityTime;
  }

  /**
   * Return the notification message.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The translatable message, or NULL.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @todo: Generalize.
   */
  public function notificationMessage(FieldableEntityInterface $entity): ?TranslatableMarkup {
    if ($this->moderationIsIgnored($entity)) {
      return NULL;
    }

    if ($entity->isNew()) {
      return $this->moderationMessage->newEntityNotificationMessage($entity);
    }

    if ($this->isEntityScheduled($entity)) {
      return NULL;
    }

    switch ($this->entityModerationState($entity)) {
      case 'unpublished':
        $deleteTime = $this->entityTime->unpublishedEntityDeleteTime($entity);
        return $deleteTime === NULL ? NULL : $this->moderationMessage->draftDeleteNotificationMessage($entity, $deleteTime);

      case 'published':
        $unpublishTime = $this->entityTime->unpublishTime($entity);
        return $unpublishTime === NULL ? NULL : $this->moderationMessage->unpublishNotificationMessage($entity, $unpublishTime);

      case 'unpublished_content':
        $archiveTime = $this->entityTime->archiveTime($entity);
        return $archiveTime === NULL ? NULL : $this->moderationMessage->archiveNotificationMessage($entity, $archiveTime);

      case 'trash':
        $deleteTime = $this->entityTime->deleteTime($entity);
        return $deleteTime === NULL ? NULL : $this->moderationMessage->oldDeleteNotificationMessage($entity, $deleteTime);

      default:
        return NULL;
    }
  }

  /**
   * Check if an entity is scheduled or not.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return bool
   *   TRUE, if it's scheduled.
   */
  public function isEntityScheduled(EntityInterface $entity): bool {
    $entityConfig = $this->lifeCycleConfig->get('fields')[$entity->getEntityTypeId()][$entity->bundle()] ?? [];

    foreach (array_keys($entityConfig) as $scheduleFieldName) {
      /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
      if ($entity->hasField($scheduleFieldName)
        && ($field = $entity->get($scheduleFieldName))
        && !$field->isEmpty()
      ) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Return the moderation state for the entity if possible.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity.
   *
   * @return string|null
   *   The state or NULL.
   */
  private function entityModerationState(FieldableEntityInterface $entity): ?string {
    if (!$entity->hasField('moderation_state')) {
      return NULL;
    }

    return $entity->get('moderation_state')->target_id ?? NULL;
  }

  /**
   * Checks whether an entity is ignored from moderation.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return bool
   *   TRUE if ignored.
   */
  protected function moderationIsIgnored(EntityInterface $entity): bool {
    return $entity->hasField('ignore_lifecycle_management')
      && (bool) $entity->get('ignore_lifecycle_management')->value === TRUE;
  }

  /**
   * Run moderation updates.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function runOperations(): void {
    if (
      ($isDisabled = $this->lifeCycleConfig->get('disabled'))
      && $isDisabled === TRUE
    ) {
      return;
    }

    // @todo: Optimize; maybe set state variables on entity update,
    // iterate through that only. E.g:
    // - tieto_lifecycle_management.operations: id => [timestamp, state].
    foreach ($this->lifeCycleConfig->get('fields') as $entityType => $bundles) {
      /** @var \Drupal\Core\Entity\EntityStorageInterface $entityStorage */
      $entityStorage = $this->entityTypeManager->getStorage($entityType);

      $entityQuery = $entityStorage->getQuery();
      $results = $entityQuery->execute();

      $entityIdsBatched = array_chunk($results, 500, TRUE);

      foreach ($entityIdsBatched as $entityIds) {
        // Note: Entities loaded are the default revision, not latest.
        // @todo: EntityCreatedInterface; see: https://www.drupal.org/node/2833378
        /** @var \Drupal\Core\Entity\EntityInterface|\Drupal\Core\Entity\EntityChangedInterface|\Drupal\Core\Entity\FieldableEntityInterface $entity */
        foreach ($entityStorage->loadMultiple($entityIds) as $entity) {
          if ($this->moderationIsIgnored($entity)) {
            continue;
          }

          if ($this->isEntityScheduled($entity)) {
            continue;
          }

          $entityId = $entity->id();

          if (
            ($isUnpublished = $this->shouldDeleteUnpublishedEntity($entity))
            || ($isOld = $this->shouldDeleteOldEntity($entity))
          ) {
            $reason = RemovalReason::UNKNOWN;
            if (isset($isUnpublished) && $isUnpublished === TRUE) {
              $reason = RemovalReason::NEVER_PUBLISHED;
            }
            if (isset($isOld) && $isOld === TRUE) {
              $reason = RemovalReason::TOO_OLD;
            }

            // @todo: More data, e.g timestamps?
            $event = new LifeCycleRemoveEvent(
              $entity,
              $reason
            );

            $this->eventDispatcher->dispatch(
              $event::NAME,
              $event
            );

            $info = json_encode([
              'id' => $entityId,
              'title' => $entity->label(),
              'url' => $entity->toUrl('canonical', ['absolute' => TRUE])
                ->toString(TRUE)
                ->getGeneratedUrl(),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $entity->delete();
            $this->logger->info("Entity ({$entityId}) has been deleted [reason: {$reason}]. Additional info: {$info}");
            continue;
          }

          $entityConfig = $bundles[$entity->bundle()] ?? [];

          $wasUpdated = FALSE;
          // Update moderation state according to the config, if users didn't
          // add a scheduled update date.
          // No need to re-update, if it already is the target state.
          // @todo: Maybe order this based on offset (DESC), so we don't update
          // multiple times.
          foreach ($entityConfig as $fieldName => $fieldSettings) {
            $fieldSettings['field_name'] = $fieldName;

            // No real reason to update multiple times.
            if ($this->shouldUpdateModerationState($entity, $fieldSettings)) {
              $wasUpdated = TRUE;

              // @todo: More data, e.g timestamps?
              $event = new LifeCycleUpdateEvent(
                $entity,
                $fieldSettings['target_state']
              );

              $this->eventDispatcher->dispatch(
                $event::NAME,
                $event
              );

              $entity->get('moderation_state')
                ->setValue($fieldSettings['target_state']);
              $entity->save();
              $this->logger->info("Entity ({$entityId}) state has been updated to {$fieldSettings['target_state']}.");
              break;
            }
          }

          // @todo?: Maybe check isset($event). If it's not, set the Ignore one.
          // @todo: If ^ is added, dispatch outside the conditional.
          if ($wasUpdated === FALSE) {
            // @todo: More data, e.g timestamps?
            $event = new LifeCycleIgnoreEvent(
              $entity
            );

            $this->eventDispatcher->dispatch(
              $event::NAME,
              $event
            );
          }
        }

        $entityStorage->resetCache($entityIds);
      }
    }

    // @todo: Dispatch LifeCycleEndedEvent (Required, when wanting to send aggregate mails).
  }

  /**
   * Returns whether the unpublished entity should be removed or not.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return bool
   *   TRUE, if the unpublished entity should be deleted.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function shouldDeleteUnpublishedEntity(EntityInterface $entity): bool {
    $draftDeleteTime = $this->entityTime->unpublishedEntityDeleteTime($entity);

    if ($draftDeleteTime === NULL) {
      return FALSE;
    }

    return $draftDeleteTime <= $this->time->getRequestTime();
  }

  /**
   * Returns whether the entity should be removed or not.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return bool
   *   TRUE, if the entity should be deleted.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function shouldDeleteOldEntity(EntityInterface $entity): bool {
    $entityDeleteTime = $this->entityTime->deleteTime($entity);

    if ($entityDeleteTime === NULL) {
      return FALSE;
    }

    return $entityDeleteTime <= $this->time->getRequestTime();
  }

  /**
   * Returns whether the entity moderation state should be updated.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity.
   * @param array $fieldSettings
   *   Field settings.
   *
   * @return bool
   *   TRUE, if the moderation state should be updated.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function shouldUpdateModerationState(
    FieldableEntityInterface $entity,
    array $fieldSettings
  ): bool {
    $currentState = $this->entityModerationState($entity);
    if ($currentState === NULL) {
      return FALSE;
    }

    // Invalid settings.
    if (empty($fieldSettings['date']) || empty($fieldSettings['target_state'])) {
      return FALSE;
    }

    $fieldName = $fieldSettings['field_name'];

    // No moderation fields, or scheduling already set by a user.
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    if (!($entity->hasField($fieldName)
      && ($field = $entity->get($fieldName))
      && $field->isEmpty())
    ) {
      return FALSE;
    }

    // Already the target state, or would be an invalid one.
    if (
      $currentState === $fieldSettings['target_state']
      || !$this->isCorrectTransition($currentState, $fieldSettings['target_state'])
    ) {
      return FALSE;
    }

    // @todo: Unify.
    if (((bool) $fieldSettings['enabled']) === FALSE) {
      return FALSE;
    }

    $moderationUpdateTime = $this->entityTime->offsetLastPublishTime($entity, $fieldSettings['date']);

    // Was not yet published.
    if ($moderationUpdateTime === NULL) {
      return FALSE;
    }

    return $this->time->getRequestTime() >= $moderationUpdateTime;
  }

  /**
   * Determines whether a transition is correct or not.
   *
   * @param string $currentState
   *   The current state.
   * @param string $targetState
   *   The desired state.
   *
   * @return bool
   *   TRUE for correct transitions, FALSE otherwise.
   *
   * @todo: temporary
   * @todo: FIXME, move to config.
   */
  public function isCorrectTransition(string $currentState, string $targetState): bool {
    switch ($currentState) {
      case 'unpublished':
        return TRUE;

      case 'published':
        return in_array($targetState, ['unpublished_content', 'trash'], TRUE);

      case 'unpublished_content':
        return $targetState === 'trash';
    }

    return FALSE;
  }

}
