<?php

namespace Drupal\tieto_lifecycle_management\Event;

/**
 * Class LifeCycleIgnoreEvent.
 *
 * @package Drupal\tieto_lifecycle_management\Event
 */
interface LifeCycleIgnoreEventInterface extends LifeCycleEventInterface {

  public const NAME = 'life_cycle_event.ignore';

}
