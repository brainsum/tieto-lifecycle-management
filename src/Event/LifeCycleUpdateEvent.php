<?php

namespace Drupal\tieto_lifecycle_management\Event;

use Drupal\Core\Entity\EntityInterface;

/**
 * Class LifeCycleUpdateEvent.
 *
 * @package Drupal\tieto_lifecycle_management\Event
 */
class LifeCycleUpdateEvent extends LifeCycleEvent implements LifeCycleUpdateEventInterface {

  private $targetState;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityInterface $entity,
    string $targetState
  ) {
    parent::__construct($entity);

    $this->targetState = $targetState;
  }

  /**
   * Returns the target state.
   *
   * @return string
   *   The target state.
   */
  public function targetState(): string {
    return $this->targetState;
  }

  /**
   * Fluent setter for the target state.
   *
   * @param string $targetState
   *   The target state.
   *
   * @return \Drupal\tieto_lifecycle_management\Event\LifeCycleUpdateEventInterface
   *   The event.
   */
  public function setTargetState(string $targetState): LifeCycleUpdateEventInterface {
    $this->targetState = $targetState;
    return $this;
  }

}
