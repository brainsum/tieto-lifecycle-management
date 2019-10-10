<?php

namespace Drupal\tieto_lifecycle_management_notifications\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\tieto_lifecycle_management_notifications\Data\EntityLifeCycleData;
use function array_merge;
use function array_unique;
use function array_values;

/**
 * Class NotificationStorage.
 *
 * @package Drupal\tieto_lifecycle_management_notifications\Service
 */
final class NotificationStorage {

  private const COLLECTION = 'tieto_lifecycle_management_notifications.notification_store';

  /**
   * The key value store for notifications.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  private $notificationStore;

  /**
   * NotificationStorage constructor.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyValueFactory
   *   Key value storage factory.
   */
  public function __construct(
    KeyValueFactoryInterface $keyValueFactory
  ) {
    $this->notificationStore = $keyValueFactory->get(static::COLLECTION);
  }

  /**
   * Turn an entity into a notification store key.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return string
   *   The storage key.
   */
  private function entityToKey(EntityInterface $entity): string {
    return "{$entity->getEntityTypeId()}.{$entity->bundle()}.{$entity->id()}";
  }

  /**
   * Turn an entity data array into a notification store key.
   *
   * @param \Drupal\tieto_lifecycle_management_notifications\Data\EntityLifeCycleData $entityData
   *   The entity data array.
   *
   * @return string
   *   The storage key.
   */
  private function entityDataToKey(EntityLifeCycleData $entityData): string {
    return "{$entityData->type}.{$entityData->bundle}.{$entityData->id}";
  }

  /**
   * Do the update.
   *
   * @param string $storageKey
   *   The storage key.
   * @param string $notificationId
   *   The notification ID.
   * @param array $userIds
   *   Array of userIds.
   */
  private function doSaveNotification(string $storageKey, string $notificationId, array $userIds): void {
    $notificationData = $this->notificationStore->get($storageKey, []);

    // Make sure we only use the values.
    $userIds = array_values($userIds);
    // Store users that already got the specific notification.
    $notificationData[$notificationId] = empty($notificationData[$notificationId])
      ? $userIds
      : array_unique(array_merge($notificationData[$notificationId], $userIds));

    $this->notificationStore->set($storageKey, $notificationData);
  }

  /**
   * Adds or updates an entity to the notification store.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be stored.
   * @param string $notificationId
   *   The notification ID.
   * @param array $userIds
   *   Array of userIds.
   */
  public function saveEntity(EntityInterface $entity, string $notificationId, array $userIds): void {
    $this->doSaveNotification($this->entityToKey($entity), $notificationId, $userIds);
  }

  /**
   * Adds or updates an entity to the notification store.
   *
   * @param \Drupal\tieto_lifecycle_management_notifications\Data\EntityLifeCycleData $entityData
   *   The entity data to be stored.
   * @param string $notificationId
   *   The notification ID.
   * @param array $userIds
   *   Array of userIds.
   */
  public function saveEntityByData(EntityLifeCycleData $entityData, string $notificationId, array $userIds): void {
    $this->doSaveNotification($this->entityDataToKey($entityData), $notificationId, $userIds);
  }

  /**
   * Removes an entity from the notification store.
   *
   * Used e.g in entity delete hooks.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be removed.
   */
  public function removeEntity(EntityInterface $entity): void {
    $this->notificationStore->delete($this->entityToKey($entity));
  }

  /**
   * Return notification data for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array
   *   The notification data for the entity.
   */
  public function entityData(EntityInterface $entity): array {
    $entityKey = $this->entityToKey($entity);

    return $this->notificationStore->get($entityKey, []);
  }

}
