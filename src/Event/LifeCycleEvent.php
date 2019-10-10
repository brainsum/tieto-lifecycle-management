<?php

namespace Drupal\tieto_lifecycle_management\Event;

use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class LifeCycleEvent.
 *
 * @package Drupal\tieto_lifecycle_management\Event
 */
abstract class LifeCycleEvent extends Event implements LifeCycleEventInterface {

  private $entity;

  /**
   * LifeCycleEvent constructor.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   ID of the entity.
   */
  public function __construct(
    EntityInterface $entity
  ) {
    $this->entity = $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function entity(): EntityInterface {
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntity(EntityInterface $entity): LifeCycleEventInterface {
    $this->entity = $entity;
    return $this;
  }

}
