<?php

namespace Drupal\tieto_lifecycle_management_notifications\Service;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserInterface;
use Exception;
use function reset;

/**
 * Class Mailer.
 *
 * @package Drupal\tieto_lifecycle_management_notifications\Service
 *
 * @todo: Maybe queue up the mails? @see: queue_mail module
 */
final class Mailer {

  use StringTranslationTrait;
  use MessengerTrait;

  private const MODULE_NAME = 'tieto_lifecycle_management_notifications';

  private $mailManager;

  private $renderer;

  private $notificationStorage;

  private $notificationSettings;

  private $logger;

  /**
   * Mailer constructor.
   *
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The mail manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\tieto_lifecycle_management_notifications\Service\NotificationStorage $notificationStorage
   *   Notification storage.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   Logger factory.
   */
  public function __construct(
    MailManagerInterface $mailManager,
    RendererInterface $renderer,
    ConfigFactoryInterface $configFactory,
    NotificationStorage $notificationStorage,
    LoggerChannelFactoryInterface $logger
  ) {
    $this->mailManager = $mailManager;
    $this->renderer = $renderer;
    $this->notificationStorage = $notificationStorage;
    $this->logger = $logger->get(static::MODULE_NAME);

    $this->notificationSettings = $configFactory->get('tieto_lifecycle_management_notifications.settings');
  }

  /**
   * Returns the user's full name, or falls back to the display name.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   *
   * @return string
   *   The name.
   */
  private function determineUserName(UserInterface $user): string {
    if (
      $user->hasField('field_user_fullname')
      && ($fullNameField = $user->get('field_user_fullname'))
      && !$fullNameField->isEmpty()
    ) {
      return $fullNameField->getString();
    }

    return $user->getDisplayName();
  }

  /**
   * Send a reminder about entity unpublish dates.
   *
   * @param \Drupal\user\UserInterface $user
   *   The target user.
   * @param \Drupal\tieto_lifecycle_management_notifications\Data\EntityLifeCycleData[] $entityData
   *   Array of EntityLifeCycleData instances.
   * @param string $reminderType
   *   Reminder type, e.g "unpublish_in_1_month", or something.
   *
   * @throws \Exception
   */
  public function sendReminder(
    UserInterface $user,
    array $entityData,
    string $reminderType
  ): void {
    if (
      ($isDisabled = $this->notificationSettings->get('disabled'))
      && $isDisabled === TRUE
    ) {
      return;
    }

    if ($user->getEmail() === NULL) {
      return;
    }

    $langCode = $user->getPreferredLangcode();
    $entityCount = count($entityData);

    if ($entityCount <= 0) {
      return;
    }

    /** @var \Drupal\tieto_lifecycle_management_notifications\Data\EntityLifeCycleData $firstEntity */
    $firstEntity = reset($entityData);
    $userName = $this->determineUserName($user);
    $contactMail = $this->notificationSettings->get('contact_mail');

    $template = [
      '#user_name' => $userName,
      '#pages' => $entityData,
      '#contact_mail' => $contactMail,
    ];

    // @todo: Cleanup.
    $subjectPrefix = '';
    if ($reminderType === 'reminder.unpublished_content.half_month_before') {
      $subjectPrefix = $this->t('REMINDER') . ' - ';
    }

    if ($entityCount === 1) {
      $subject = $subjectPrefix . $this->t(
        'TO BE UNPUBLISHED: Your Intra page - @pageTitle',
        [
          '@pageTitle' => $firstEntity->title,
        ],
        [
          'langcode' => $langCode,
        ]
      );

      $template['#entity_unpublish_date'] = $firstEntity->unpublishDate;
      $template['#theme'] = 'single_content_unpublish__reminder';
    }
    else {
      $subject = $subjectPrefix . $this->t(
        'TO BE UNPUBLISHED: Multiple Intra pages',
        [],
        [
          'langcode' => $langCode,
        ]
      );

      $template['#theme'] = 'multiple_content_unpublish__reminder';
    }

    try {
      $rendered = Html::escape($this->renderer->renderRoot($template));
    }
    catch (Exception $exception) {
      $this->logger->error('Sending life-cycle reminder failed. ' . $exception->getMessage());
      return;
    }

    $params = [
      'subject' => $subject,
      'message' => $rendered,
    ];

    $result = $this->mailManager->mail(
      static::MODULE_NAME,
      'life_cycle_notification__unpublish_reminder',
      $user->getEmail(),
      $user->getPreferredLangcode(),
      $params,
      $contactMail
    );

    // @todo: More messenger data.
    // @todo: Maybe log instead of messenger.
    if ($result['result'] === TRUE) {
      foreach ($entityData as $data) {
        $this->notificationStorage->saveEntityByData($data, $reminderType, [$user->id()]);
      }

      $this->messenger()
        ->addStatus($this->t('Reminder sent to @user.', [
          '@user' => $userName,
        ]));
    }
    else {
      $this->messenger()
        ->addError($this->t('Sending the reminder failed.'));
    }
  }

  /**
   * Send a notification about entities getting unpublished.
   *
   * @param \Drupal\user\UserInterface $user
   *   The target user.
   * @param \Drupal\tieto_lifecycle_management_notifications\Data\EntityLifeCycleData[] $entityData
   *   Array of EntityLifeCycleData instances.
   * @param string $notificationType
   *   Notification type, e.g "unpublished", or something.
   */
  public function sendNotification(
    UserInterface $user,
    array $entityData,
    string $notificationType
  ): void {
    if (
      ($isDisabled = $this->notificationSettings->get('disabled'))
      && $isDisabled === TRUE
    ) {
      return;
    }

    if ($user->getEmail() === NULL) {
      return;
    }

    $langCode = $user->getPreferredLangcode();
    $entityCount = count($entityData);

    if ($entityCount <= 0) {
      return;
    }

    /** @var \Drupal\tieto_lifecycle_management_notifications\Data\EntityLifeCycleData $firstEntity */
    $firstEntity = reset($entityData);
    $userName = $this->determineUserName($user);
    $contactMail = $this->notificationSettings->get('contact_mail');

    $template = [
      '#user_name' => $userName,
      '#pages' => $entityData,
      '#contact_mail' => $contactMail,
    ];

    if ($entityCount === 1) {
      $subject = $this->t(
        'UNPUBLISHED: Your Intra page - @pageTitle',
        [
          '@pageTitle' => $firstEntity->title,
        ],
        [
          'langcode' => $langCode,
        ]
      );

      $template['#entity_delete_date'] = $firstEntity->deleteDate;
      $template['#theme'] = 'single_content_unpublish__notification';
    }
    else {
      $subject = $this->t(
        'UNPUBLISHED: Multiple Intra pages',
        [],
        [
          'langcode' => $langCode,
        ]
      );

      $template['#theme'] = 'multiple_content_unpublish__notification';
    }

    try {
      $rendered = Html::escape($this->renderer->renderRoot($template));
    }
    catch (Exception $exception) {
      $this->logger->error('Sending life-cycle notification failed. ' . $exception->getMessage());
      return;
    }

    $params = [
      'subject' => $subject,
      'message' => $rendered,
    ];

    $result = $this->mailManager->mail(
      static::MODULE_NAME,
      'life_cycle_notification__unpublish_notification',
      $user->getEmail(),
      $user->getPreferredLangcode(),
      $params,
      $contactMail
    );

    // @todo: More messenger data.
    // @todo: Maybe log instead of messenger.
    if ($result['result'] === TRUE) {
      foreach ($entityData as $data) {
        $this->notificationStorage->saveEntityByData($data, $notificationType, [$user->id()]);
      }

      $this->messenger()
        ->addStatus($this->t('Notification sent to @user.', [
          '@user' => $userName,
        ]));
    }
    else {
      $this->messenger()
        ->addError($this->t('Sending the notification failed.'));
    }
  }

}
