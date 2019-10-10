<?php

namespace Drupal\tieto_lifecycle_management\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use function array_filter;
use function date_parse;

/**
 * Class ConfigurationForm.
 *
 * @package Drupal\tieto_lifecycle_management\Form
 *
 * @see: tieto_lifecycle_management.configuration_form
 */
final class ConfigurationForm extends ConfigFormBase {

  private $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * ConfigurationForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct($configFactory);

    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      'tieto_lifecycle_management.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'tieto_lifecycle_management_configuration_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $form['#tree'] = TRUE;
    $form['disabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disabled'),
      '#description' => $this->t('Check if you want to disable life-cycle management globally.'),
      '#default_value' => $this->config('tieto_lifecycle_management.settings')->get('disabled') ?? FALSE,
    ];
    $form['fields'] = $this->fieldsElement();
    $form['lfc_actions'] = $this->actionsElement();
    return $form;
  }

  /**
   * Returns the "actions" element for the form.
   *
   * @return array
   *   The array.
   */
  protected function actionsElement(): array {
    // @todo: Allow adding new ones.
    $element = [
      '#type' => 'fieldset',
      '#title' => $this->t('Actions'),
    ];

    foreach ($this->config('tieto_lifecycle_management.settings')->get('actions') as $targetEntityId => $typeData) {

      if (!isset($element[$targetEntityId])) {
        $element[$targetEntityId] = [
          '#type' => 'fieldset',
          '#title' => $targetEntityId,
        ];
      }

      foreach ($typeData as $targetBundleId => $bundleData) {

        if (!isset($element[$targetEntityId][$targetBundleId])) {
          $element[$targetEntityId][$targetBundleId] = [
            '#type' => 'fieldset',
            '#title' => $targetBundleId,
          ];
        }

        foreach ($bundleData as $actionId => $settings) {
          $element[$targetEntityId][$targetBundleId][$actionId] = [
            '#type' => 'fieldset',
            '#title' => $actionId,
            'enabled' => [
              '#type' => 'checkbox',
              '#title' => $this->t('Enabled'),
              '#default_value' => $settings['enabled'] ?? TRUE,
            ],
            'date' => [
              '#type' => 'textfield',
              '#title' => $this->t('Date'),
              '#placeholder' => '+6 months',
              '#default_value' => $settings['date'] ?? '',
            ],
          ];
        }
      }
    }

    return $element;
  }

  /**
   * Returns the "fields" element for the form.
   *
   * @return array
   *   The array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function fieldsElement(): array {
    /** @var \Drupal\field\FieldConfigInterface[] $scheduledFields */
    $scheduledFields = $this
      ->entityTypeManager
      ->getStorage('field_config')
      ->loadByProperties([
        'field_type' => 'entity_reference',
      ]);

    $scheduledFields = array_filter($scheduledFields, static function ($field) {
      /** @var \Drupal\field\FieldConfigInterface $field */
      return $field->getSetting('handler') === 'default:scheduled_update';
    });

    $values = $this->config('tieto_lifecycle_management.settings')
      ->get('fields');
    $element = [
      '#type' => 'fieldset',
      '#title' => $this->t('Fields'),
    ];

    foreach ($scheduledFields as $scheduledField) {
      $targetEntityId = $scheduledField->getTargetEntityTypeId();
      $targetBundleId = $scheduledField->getTargetBundle();
      $fieldId = $scheduledField->getName();

      if (!isset($element[$targetEntityId])) {
        $element[$targetEntityId] = [
          '#type' => 'fieldset',
          '#title' => $targetEntityId,
        ];
      }

      if (!isset($element[$targetEntityId][$targetBundleId])) {
        $element[$targetEntityId][$targetBundleId] = [
          '#type' => 'fieldset',
          '#title' => $targetBundleId,
        ];
      }

      $element[$targetEntityId][$targetBundleId][$fieldId] = [
        '#type' => 'fieldset',
        '#title' => $fieldId,
        'enabled' => [
          '#type' => 'checkbox',
          '#title' => $this->t('Enabled'),
          '#default_value' => $values[$targetEntityId][$targetBundleId][$fieldId]['enabled'] ?? TRUE,
        ],
        'date' => [
          '#type' => 'textfield',
          '#title' => $this->t('Date'),
          '#placeholder' => '+6 months',
          '#default_value' => $values[$targetEntityId][$targetBundleId][$fieldId]['date'] ?? '',
        ],
        // @todo: Load from field as markup only?
        'target_state' => [
          '#type' => 'textfield',
          '#title' => $this->t('Target state'),
          '#placeholder' => 'E.g published',
          '#default_value' => $values[$targetEntityId][$targetBundleId][$fieldId]['target_state'] ?? '',
        ],
      ];
    }

    return $element;
  }

  /**
   * Validate a date string.
   *
   * @param string $date
   *   The date string.
   *
   * @return bool
   *   TRUE, if date is valid.
   */
  protected function isRelativeDate(string $date): bool {
    $parsedDate = date_parse($date);

    // @todo: Disallow negative numbers.
    return !(
      $parsedDate === FALSE
      || (isset($parsedDate['error_count']) && $parsedDate['error_count'] > 0)
      || !isset($parsedDate['relative'])
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    foreach ($values['fields'] as $entityType => $bundles) {
      foreach ($bundles as $bundle => $fields) {
        foreach ($fields as $field => $fieldValues) {
          $dateValue = $fieldValues['date'] ?? NULL;

          if (
            !empty($dateValue)
            && !$this->isRelativeDate($dateValue)
          ) {
            $form_state->setError($form['fields'][$entityType][$bundle][$field]['date'], $this->t('@value is not a valid relative date.', [
              '@value' => $dateValue,
            ]));
          }
        }
      }
    }

    foreach ($values['lfc_actions'] as $entityType => $bundles) {
      foreach ($bundles as $bundle => $fields) {
        foreach ($fields as $field => $fieldValues) {
          $dateValue = $fieldValues['date'] ?? NULL;

          if (
            !empty($dateValue)
            && !$this->isRelativeDate($dateValue)
          ) {
            $form_state->setError($form['lfc_actions'][$entityType][$bundle][$field]['date'], $this->t('@value is not a valid relative date.', [
              '@value' => $dateValue,
            ]));
          }
        }
      }
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    $config = $this->config('tieto_lifecycle_management.settings');
    $config->set('disabled', (bool) $values['disabled']);
    $config->set('actions', $values['lfc_actions']);
    $config->set('fields', $values['fields']);
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
