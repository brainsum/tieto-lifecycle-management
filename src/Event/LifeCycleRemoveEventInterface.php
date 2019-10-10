<?php

namespace Drupal\tieto_lifecycle_management\Event;

/**
 * Interface LifeCycleEventInterface.
 *
 * @package Drupal\tieto_lifecycle_management\Event
 */
interface LifeCycleRemoveEventInterface extends LifeCycleEventInterface {

  public const NAME = 'life_cycle_event.remove';

  /**
   * Returns the removal reason.
   *
   * @return string
   *   The removal reason.
   */
  public function removalReason(): string;

  /**
   * Fluent setter for the removal reason.
   *
   * @param string $removalReason
   *   The removal reason.
   *
   * @return \Drupal\tieto_lifecycle_management\Event\LifeCycleRemoveEventInterface
   *   Return the event.
   */
  public function setRemovalReason(string $removalReason): LifeCycleRemoveEventInterface;

}
