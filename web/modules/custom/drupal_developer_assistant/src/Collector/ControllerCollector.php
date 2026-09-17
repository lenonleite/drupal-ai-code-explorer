<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Collector;

use Drupal\drupal_developer_assistant\Model\ControllerComponent;
use Drupal\drupal_developer_assistant\Model\RouteComponent;

/**
 * Collects controller classes referenced by compiled Drupal routes.
 */
final class ControllerCollector implements CollectorInterface {

  /**
   * Constructs a controller collector.
   */
  public function __construct(
    private readonly RouteCollector $routeCollector,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'controllers';
  }

  /**
   * {@inheritdoc}
   */
  public function collect(): array {
    $controllers = [];
    foreach ($this->routeCollector->collect() as $route) {
      if (!$route instanceof RouteComponent) {
        throw new \UnexpectedValueException('The route collector must return RouteComponent objects.');
      }
      if (
        $route->targetType !== 'controller'
        || $route->target === NULL
        || $route->targetClass === NULL
      ) {
        continue;
      }

      $class_name = $route->targetClass;
      $controllers[$class_name] ??= [
        'route_paths' => [],
        'route_callbacks' => [],
        'service_ids' => [],
        'source_path' => NULL,
      ];
      $controllers[$class_name]['route_paths'][$route->id] = $route->label;
      $controllers[$class_name]['route_callbacks'][$route->id]
        = $this->callbackMethod($route->target);

      $service_id = $this->serviceId($route->target);
      if ($service_id !== NULL) {
        $controllers[$class_name]['service_ids'][$service_id] = TRUE;
      }
      $controllers[$class_name]['source_path'] ??= $route->sourcePath;
    }
    ksort($controllers, SORT_STRING);

    $records = [];
    foreach ($controllers as $class_name => $controller) {
      ksort($controller['route_paths'], SORT_STRING);
      ksort($controller['route_callbacks'], SORT_STRING);
      $service_ids = array_keys($controller['service_ids']);
      sort($service_ids, SORT_STRING);

      $records[] = new ControllerComponent(
        className: $class_name,
        routePaths: $controller['route_paths'],
        routeCallbacks: $controller['route_callbacks'],
        serviceIds: $service_ids,
        sourcePath: $controller['source_path'],
      );
    }

    return $records;
  }

  /**
   * Extracts the method from a controller callback.
   */
  private function callbackMethod(string $target): string {
    if (str_contains($target, '::')) {
      [, $method] = explode('::', $target, 2);
      return $method;
    }

    if (str_contains($target, ':')) {
      [, $method] = explode(':', $target, 2);
      return $method;
    }

    return '__invoke';
  }

  /**
   * Extracts a service ID from a service-based controller callback.
   */
  private function serviceId(string $target): ?string {
    if (str_contains($target, '::')) {
      return NULL;
    }

    if (str_contains($target, ':')) {
      [$candidate] = explode(':', $target, 2);
    }
    else {
      $candidate = $target;
    }

    return str_contains($candidate, '\\') ? NULL : $candidate;
  }

}
