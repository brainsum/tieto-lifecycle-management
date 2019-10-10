<?php

namespace Drupal\tieto_lifecycle_management\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Class ModerationMessage.
 *
 * @package Drupal\tieto_lifecycle_management\Service
 */
final class ModerationMessage {

  private const DATE_FORMAT = 'tieto_date';

  private $dateFormatter;

  private $translation;

  /**
   * Configured messages.
   *
   * Structure: $messages['content_type']['bundle']['message_type'] = message.
   *
   * Use 'default_message' as the bundle as the fallback. This is useful, when
   * only a few bundles have to be customized, the rest have the same message.
   *
   * Message types:
   * 'new',
   * 'draft_delete',
   * 'unpublish',
   * 'archive',
   * 'old_delete',
   *
   * Message:
   * May contain placeholders, those have to be handled here, e.g "@deleteDate".
   * Currently handled placeholders for message types:
   * 'draft_delete' => "@deleteDate",
   * 'unpublish' => "@unpublishDate",
   * 'archive' => "@archiveDate",
   * 'old_delete' => "@deleteDate",
   *
   * @var array
   */
  private $messages;

  /**
   * ModerationMessage constructor.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   Date formatter service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   Translation service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   */
  public function __construct(
    DateFormatterInterface $dateFormatter,
    TranslationInterface $translation,
    ConfigFactoryInterface $configFactory
  ) {
    $this->dateFormatter = $dateFormatter;
    $this->translation = $translation;
    $this->messages = $configFactory->get('tieto_lifecycle_management.settings')->get('messages') ?? [];
  }

  /**
   * Format a timestamp.
   *
   * @param int $timestamp
   *   The timestamp.
   *
   * @return string
   *   The formatted timestamp.
   */
  protected function formatTimestamp(int $timestamp): string {
    return $this->dateFormatter->format($timestamp, static::DATE_FORMAT);
  }

  /**
   * Return the message for the given parameters.
   *
   * @param string $contentType
   *   Content type, e.g node.
   * @param string $bundle
   *   Bundle, e.g article.
   *   Fallback message can be set with the 'default_message' as the bundle.
   * @param string $messageType
   *   Type of the message.
   *   Supported types:
   *   - 'new',
   *   - 'draft_delete',
   *   - 'unpublish',
   *   - 'archive',
   *   - 'old_delete'.
   *
   * @return string|null
   *   The message or NULL.
   */
  protected function message(string $contentType, string $bundle, string $messageType): ?string {
    if (!empty($this->messages[$contentType][$bundle][$messageType])) {
      return $this->messages[$contentType][$bundle][$messageType];
    }

    if (!empty($this->messages[$contentType]['default_message'][$messageType])) {
      return $this->messages[$contentType]['default_message'][$messageType];
    }

    return NULL;
  }

  /**
   * Return notification about new entities.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The translatable message, or NULL.
   */
  public function newEntityNotificationMessage(FieldableEntityInterface $entity): ?TranslatableMarkup {
    if ($entity->isNew() === FALSE) {
      return NULL;
    }

    $message = $this->message($entity->getEntityTypeId(), $entity->bundle(), 'new');
    return $message === NULL ? NULL : $this->translation->translate($message);
  }

  /**
   * Return notification about draft deletion.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity.
   * @param int $timestamp
   *   Delete timestamp.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The translatable message, or NULL.
   */
  public function draftDeleteNotificationMessage(FieldableEntityInterface $entity, int $timestamp): ?TranslatableMarkup {
    $message = $this->message($entity->getEntityTypeId(), $entity->bundle(), 'draft_delete');
    return $message === NULL ? NULL : $this->translation->translate($message, [
      '@deleteDate' => $this->formatTimestamp($timestamp),
    ]);
  }

  /**
   * Return notification about unpublishing.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity.
   * @param int $timestamp
   *   The timestamp.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The translatable message, or NULL.
   */
  public function unpublishNotificationMessage(FieldableEntityInterface $entity, int $timestamp): ?TranslatableMarkup {
    $message = $this->message($entity->getEntityTypeId(), $entity->bundle(), 'unpublish');
    return $message === NULL ? NULL : $this->translation->translate($message, [
      '@unpublishDate' => $this->formatTimestamp($timestamp),
    ]);
  }

  /**
   * Return notification about archiving.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity.
   * @param int $timestamp
   *   The timestamp.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The translatable message, or NULL.
   */
  public function archiveNotificationMessage(FieldableEntityInterface $entity, int $timestamp): ?TranslatableMarkup {
    $message = $this->message($entity->getEntityTypeId(), $entity->bundle(), 'archive');
    return $message === NULL ? NULL : $this->translation->translate($message, [
      '@archiveDate' => $this->formatTimestamp($timestamp),
    ]);
  }

  /**
   * Return notification about deleting old entities.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity.
   * @param int $timestamp
   *   The timestamp.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The translatable message, or NULL.
   */
  public function oldDeleteNotificationMessage(FieldableEntityInterface $entity, int $timestamp): ?TranslatableMarkup {
    $message = $this->message($entity->getEntityTypeId(), $entity->bundle(), 'old_delete');
    return $message === NULL ? NULL : $this->translation->translate($message, [
      '@deleteDate' => $this->formatTimestamp($timestamp),
    ]);
  }

}
