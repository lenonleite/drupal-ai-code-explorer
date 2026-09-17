<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides the reusable component-list filter form.
 */
final class ComponentFilterForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'drupal_developer_assistant_component_filter';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    string $route_name = '',
    string $filter = '',
    array $route_parameters = [],
  ): array {
    $form['#method'] = 'get';
    $form['#action'] = Url::fromRoute(
      $route_name,
      $route_parameters,
    )->toString();

    $form['filter'] = [
      '#type' => 'search',
      '#title' => $this->t('Filter'),
      '#default_value' => $filter,
      '#size' => 40,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply filter'),
      '#name' => '',
    ];

    if ($filter !== '') {
      $form['actions']['reset'] = [
        '#type' => 'link',
        '#title' => $this->t('Clear'),
        '#url' => Url::fromRoute($route_name, $route_parameters),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // The destination controller reads the filter from the GET query string.
  }

}
