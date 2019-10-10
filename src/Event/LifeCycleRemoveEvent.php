<?php

namespace Drupal\tieto_lifecycle_management\Event;

use Drupal\Core\Entity\EntityInterface;

/**
 * Class LifeCycleRemoveEvent.
 *
 * @package Drupal\tieto_lifecycle_management\Event
 */
class LifeCycleRemoveEvent extends LifeCycleEvent implements LifeCycleRemoveEventInterface {

  private $removalReason;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityInterface $entity,
    string $removalReason
  ) {
    parent::__construct($entity);

    $this->removalReason = $removalReason;
  }

  /**
   * {@inheritdoc}
   */
  public function removalReason(): string {
    return $this->removalReason;
  }

  /**
   * {@inheritdoc}
   */
  public function setRemovalReason(string $removalReason): LifeCycleRemoveEventInterface {
    $this->removalReason = $removalReason;
    return $this;
  }

}
