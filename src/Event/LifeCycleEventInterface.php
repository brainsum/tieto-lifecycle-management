<?php

namespace Drupal\tieto_lifecycle_management\Event;

use Drupal\Core\Entity\EntityInterface;

/**
 * Interface LifeCycleEventInterface.
 *
 * @package Drupal\tieto_lifecycle_management\Event
 */
interface LifeCycleEventInterface {

  /**
   * Return entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity.
   */
  public function entity(): EntityInterface;

  /**
   * Fluent setter for the entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\tieto_lifecycle_management\Event\LifeCycleEventInterface
   *   Return the event.
   */
  public function setEntity(EntityInterface $entity): LifeCycleEventInterface;

}
