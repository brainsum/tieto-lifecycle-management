<?php

namespace Drupal\tieto_lifecycle_management_notifications\Form;

use Drupal\Core\Form\ConfigFormBase;

/**
 * Class ConfigurationForm.
 *
 * @package Drupal\tieto_lifecycle_management_notifications\Form
 */
final class ConfigurationForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'tieto_lifecycle_management_notifications_configuration_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      'tieto_lifecycle_management_notifications.settings',
    ];
  }

}
