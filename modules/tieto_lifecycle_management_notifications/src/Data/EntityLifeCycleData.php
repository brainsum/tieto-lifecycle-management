<?php

namespace Drupal\tieto_lifecycle_management_notifications\Data;

/**
 * Data class for entity data used for lifecycle management.
 *
 * Defines magic functions so we can just pass an array to construct
 * the instance.
 * Defines __set to disallow re-setting properties, adding new one dynamically
 * and to hide errors/notices.
 * Defines __get to allow access to properties and to hide errors/notices.
 *
 * @todo: Maybe enforce some properties to be required (e.g ID).
 *
 * @package Drupal\tieto_lifecycle_management_notifications\Data
 *
 * @property-read int $id
 * @property-read string $type
 * @property-read string $bundle
 * @property-read string $title
 * @property-read string $url
 * @property-read string|null $moderationState
 * @property-read string|null $unpublishDate
 * @property-read int|null $unpublishTime
 * @property-read string|null $deleteDate
 * @property-read int|null $deleteTime
 *
 * @devnote: Expanding this class has two steps:
 * - Add the property to the class as protected.
 * - Add the "property-read" annotation with the proper type hint.
 */
final class EntityLifeCycleData {

  /**
   * Entity ID.
   *
   * @var int
   */
  protected $id;

  /**
   * Entity type.
   *
   * @var string
   */
  protected $type;

  /**
   * Entity bundle.
   *
   * @var string
   */
  protected $bundle;

  /**
   * Entity title.
   *
   * @var string
   */
  protected $title;

  /**
   * Absolute URL of the entity.
   *
   * @var string
   */
  protected $url;

  /**
   * Moderation state machine name or NULL.
   *
   * @var string|null
   */
  protected $moderationState;

  /**
   * Formatted entity unpublish date or NULL.
   *
   * @var string|null
   */
  protected $unpublishDate;

  /**
   * Entity unpublish timestamp or NULL.
   *
   * @var int|null
   */
  protected $unpublishTime;

  /**
   * Formatted entity delete date or NULL.
   *
   * @var string|null
   */
  protected $deleteDate;

  /**
   * Entity delete timestamp or NULL.
   *
   * @var int|null
   */
  protected $deleteTime;

  /**
   * EntityLifeCycleData constructor.
   *
   * @param array $values
   *   Array of values.
   */
  public function __construct(array $values) {
    foreach ($values as $name => $value) {
      $this->{$name} = $value;
    }
  }

  /**
   * Returns the stored values as an array.
   *
   * @return array
   *   The values.
   */
  public function values(): array {
    return get_object_vars($this);
  }

  /**
   * Returns the stored values as an array.
   *
   * @return array
   *   The values.
   */
  public function toArray(): array {
    return $this->values();
  }

  /**
   * Protects against re-setting properties and adding new ones dynamically.
   *
   * @param string $name
   *   Property name.
   * @param mixed $value
   *   Value to be set.
   */
  public function __set(string $name, $value): void {}

  /**
   * If the given property exists, returns its value, otherwise, returns NULL.
   *
   * Protects against "undefined property" notices.
   *
   * @param string $name
   *   The property name.
   *
   * @return mixed|null
   *   The property value.
   */
  public function __get(string $name) {
    if (property_exists(static::class, $name)) {
      return $this->{$name};
    }

    return NULL;
  }

  /**
   * Checks if the given property is set or not.
   *
   * @param string $name
   *   Property name.
   *
   * @return bool
   *   TRUE, if it's set.
   */
  public function __isset(string $name): bool {
    return isset($this->{$name});
  }

}
