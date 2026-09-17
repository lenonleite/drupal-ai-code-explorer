<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\drupal_developer_assistant\Architecture\ModuleArchitectureCacheInterface;

/**
 * Invalidates one module's locally discovered architecture cache.
 */
final class ModuleArchitectureRefreshForm extends FormBase {

  /**
   * Constructs the module architecture refresh form.
   */
  public function __construct(
    private readonly ModuleArchitectureCacheInterface $architectureCache,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'drupal_developer_assistant_module_architecture_refresh';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    string $module_id = '',
  ): array {
    $form['module_id'] = [
      '#type' => 'value',
      '#value' => $module_id,
    ];
    $form['description'] = [
      '#markup' => $this->t('Use this after changing module code to discard the saved architecture and scan it again.'),
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['refresh'] = [
      '#type' => 'submit',
      '#value' => $this->t('Refresh architecture'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $module_id = (string) $form_state->getValue('module_id');
    $this->architectureCache->invalidate($module_id);
    $this->messenger()->addStatus($this->t(
      'The architecture cache for @module was refreshed.',
      ['@module' => $module_id],
    ));
    $form_state->setRedirect(
      'drupal_developer_assistant.module_overview',
      ['module_id' => $module_id],
    );
  }

}
