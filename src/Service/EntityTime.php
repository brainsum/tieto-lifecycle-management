<?php

namespace Drupal\tieto_lifecycle_management\Service;

use DateInterval;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class EntityTime.
 *
 * @package Drupal\tieto_lifecycle_management\Service
 *
 * @todo: Add to moderation helper.
 */
final class EntityTime {

  private $lifeCycleConfig;

  private $entityTypeManager;

  /**
   * EntityTime constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    $this->lifeCycleConfig = $configFactory->get('tieto_lifecycle_management.settings');
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Adds "offset" to the timestamp.
   *
   * @param int $timestamp
   *   The timestamp.
   * @param string $offset
   *   The offset string, e.g "+1 month".
   *
   * @return int
   *   The timestamp with the offset added.
   */
  public function addOffset(int $timestamp, string $offset): int {
    $date = DrupalDateTime::createFromTimestamp($timestamp);
    $date->add(DateInterval::createFromDateString($offset));
    return $date->getTimestamp();
  }

  /**
   * Subtract "offset" from the timestamp.
   *
   * @param int $timestamp
   *   The timestamp.
   * @param string $offset
   *   The offset string, e.g "+1 month".
   *
   * @return int
   *   The timestamp with the offset subtracted.
   */
  public function subtractOffset(int $timestamp, string $offset): int {
    $date = DrupalDateTime::createFromTimestamp($timestamp);
    $date->sub(DateInterval::createFromDateString($offset));
    return $date->getTimestamp();
  }

  /**
   * Return the last published timestamp with an offset.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $offset
   *   The offset.
   *
   * @return int|null
   *   The last publish date timestamp with the offset applied, or NULL.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function offsetLastPublishTime(EntityInterface $entity, string $offset): ?int {
    $lastPublishDate = $this->lastPublishTime($entity);

    return $lastPublishDate
      ? $this->addOffset($lastPublishDate, $offset)
      : NULL;
  }

  /**
   * Returns the delete time of an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return int|null
   *   Return the delete time, if possible.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function deleteTime(EntityInterface $entity): ?int {
    $offset = $this->getActionOffset($entity, 'delete_published_entity');

    return $offset
      ? $this->offsetLastPublishTime($entity, $offset)
      : NULL;
  }

  /**
   * Returns the delete time of a never unpublished entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return int|null
   *   Return the delete time, if possible.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function unpublishedEntityDeleteTime(EntityInterface $entity): ?int {
    $lastPublishDate = $this->lastPublishTime($entity);

    // If it has a published date, ignore.
    if ($lastPublishDate !== NULL) {
      return NULL;
    }

    $offset = $this->getActionOffset($entity, 'delete_unpublished_entity');
    // @todo: Don't assume EntityChangedInterface, enforce it.
    /** @var \Drupal\Core\Entity\EntityChangedInterface $entity */
    return $offset
      ? $this->addOffset($entity->getChangedTime(), $offset)
      : NULL;
  }

  /**
   * Return the latest published revision, if possible.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return int|null
   *   The last published date or NULL.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function lastPublishTime(EntityInterface $entity): ?int {
    if ($entity->isNew()) {
      return NULL;
    }

    $keys = $entity->getEntityType()->getKeys();

    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    $query = $storage->getQuery();
    $query->condition($keys['id'], $entity->id());
    $query->condition($keys['status'], 1);
    $query->condition('moderation_state', 'published');
    $query->sort($keys['revision'], 'desc');
    $query->range(0, 1);
    $query->allRevisions();
    $data = $query->execute();

    if (empty($data)) {
      return NULL;
    }

    /** @var \Drupal\Core\Entity\EntityInterface|\Drupal\Core\Entity\EntityChangedInterface $revision */
    $revision = $storage->loadRevision(key($data));

    return $revision
      ? $revision->getChangedTime()
      : NULL;
  }

  /**
   * Returns the entity unpublish time if possible.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return int|null
   *   The unpublish time or NULL.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function unpublishTime(EntityInterface $entity): ?int {
    $offset = $this->getStateOffset($entity, 'unpublished_content');

    return $offset
      ? $this->offsetLastPublishTime($entity, $offset)
      : NULL;
  }

  /**
   * Returns the entity archive time if possible.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return int|null
   *   The archive time or NULL.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function archiveTime(EntityInterface $entity): ?int {
    $offset = $this->getStateOffset($entity, 'trash');

    return $offset
      ? $this->offsetLastPublishTime($entity, $offset)
      : NULL;
  }

  /**
   * Return the offset for a state.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $state
   *   The state.
   *
   * @return string|null
   *   The date offset or NULL.
   */
  private function getStateOffset(EntityInterface $entity, string $state): ?string {
    $config = $this->lifeCycleConfig->get('fields')[$entity->getEntityTypeId()][$entity->bundle()] ?? [];

    foreach ($config as $setting) {
      if (
        $setting['target_state'] === $state
        && !empty($setting['date'])
        && ((bool) $setting['enabled']) === TRUE
      ) {
        return $setting['date'];
      }
    }

    return NULL;
  }

  /**
   * Return the offset for an action.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $action
   *   The action.
   *
   * @return string|null
   *   The date offset or NULL.
   */
  private function getActionOffset(EntityInterface $entity, string $action): ?string {
    $config = $this->lifeCycleConfig->get('actions')[$entity->getEntityTypeId()][$entity->bundle()] ?? [];

    if (
      isset($config[$action])
      && ((bool) $config[$action]['enabled']) === TRUE
    ) {
      return $config[$action]['date'];
    }

    return NULL;
  }

}
