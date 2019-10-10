<?php

namespace Drupal\tieto_lifecycle_management\Event;

/**
 * Interface LifeCycleEventInterface.
 *
 * @package Drupal\tieto_lifecycle_management\Event
 */
interface LifeCycleUpdateEventInterface extends LifeCycleEventInterface {

  public const NAME = 'life_cycle_event.update';

  /**
   * Returns the target state.
   *
   * @return string
   *   The target state.
   */
  public function targetState(): string;

  /**
   * Fluent setter for the target state.
   *
   * @param string $targetState
   *   The target state.
   *
   * @return \Drupal\tieto_lifecycle_management\Event\LifeCycleUpdateEventInterface
   *   The event.
   */
  public function setTargetState(string $targetState): LifeCycleUpdateEventInterface;

}
