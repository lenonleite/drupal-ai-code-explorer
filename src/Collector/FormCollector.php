<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Collector;

use Drupal\drupal_developer_assistant\Model\FormComponent;
use Drupal\drupal_developer_assistant\Model\RouteComponent;

/**
 * Collects form classes referenced by compiled Drupal routes.
 */
final class FormCollector implements CollectorInterface {

  /**
   * Constructs a form collector.
   */
  public function __construct(
    private readonly RouteCollector $routeCollector,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'forms';
  }

  /**
   * {@inheritdoc}
   */
  public function collect(): array {
    $forms = [];
    foreach ($this->routeCollector->collect() as $route) {
      if (!$route instanceof RouteComponent) {
        throw new \UnexpectedValueException('The route collector must return RouteComponent objects.');
      }
      if (
        !in_array($route->targetType, ['form', 'entity_form'], TRUE)
        || $route->target === NULL
        || $route->targetClass === NULL
      ) {
        continue;
      }

      $class_name = $route->targetClass;
      $forms[$class_name] ??= [
        'route_paths' => [],
        'route_types' => [],
        'route_targets' => [],
        'source_path' => NULL,
      ];
      $forms[$class_name]['route_paths'][$route->id] = $route->label;
      $forms[$class_name]['route_types'][$route->id] = $route->targetType;
      $forms[$class_name]['route_targets'][$route->id] = $route->target;
      $forms[$class_name]['source_path'] ??= $route->sourcePath;
    }
    ksort($forms, SORT_STRING);

    $records = [];
    foreach ($forms as $class_name => $form) {
      ksort($form['route_paths'], SORT_STRING);
      ksort($form['route_types'], SORT_STRING);
      ksort($form['route_targets'], SORT_STRING);

      $records[] = new FormComponent(
        className: $class_name,
        routePaths: $form['route_paths'],
        routeTypes: $form['route_types'],
        routeTargets: $form['route_targets'],
        sourcePath: $form['source_path'],
      );
    }

    return $records;
  }

}
