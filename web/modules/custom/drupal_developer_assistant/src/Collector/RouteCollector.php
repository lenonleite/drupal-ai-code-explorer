<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Collector;

use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\drupal_developer_assistant\Model\EntityTypeComponent;
use Drupal\drupal_developer_assistant\Model\RouteComponent;
use Drupal\drupal_developer_assistant\Model\ServiceComponent;
use Drupal\drupal_developer_assistant\Resolver\SourcePathResolverInterface;
use Symfony\Component\Routing\Route;

/**
 * Collects compiled Drupal routes and resolves their execution targets.
 */
final class RouteCollector implements CollectorInterface {

  /**
   * Route defaults that identify the execution target.
   */
  private const array TARGET_DEFAULTS = [
    '_controller' => 'controller',
    '_form' => 'form',
    '_entity_form' => 'entity_form',
    '_entity_list' => 'entity_list',
    '_entity_view' => 'entity_view',
  ];

  /**
   * Constructs a route collector.
   */
  public function __construct(
    private readonly RouteProviderInterface $routeProvider,
    private readonly ServiceCollector $serviceCollector,
    private readonly EntityTypeCollector $entityTypeCollector,
    private readonly SourcePathResolverInterface $sourcePathResolver,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'routes';
  }

  /**
   * {@inheritdoc}
   */
  public function collect(): array {
    $routes = [];
    foreach ($this->routeProvider->getAllRoutes() as $route_name => $route) {
      if (!$route instanceof Route) {
        throw new \UnexpectedValueException('The route provider must return Symfony Route objects.');
      }
      $routes[$route_name] = $route;
    }
    ksort($routes, SORT_STRING);

    $service_classes = $this->serviceClasses();
    $entity_types = $this->entityTypes();
    $records = [];

    foreach ($routes as $route_name => $route) {
      [$target_type, $target] = $this->executionTarget($route);
      $target_class = $this->resolveTargetClass(
        $target_type,
        $target,
        $service_classes,
        $entity_types,
      );

      $requirements = [];
      foreach ($route->getRequirements() as $name => $requirement) {
        if (is_scalar($requirement)) {
          $requirements[$name] = (string) $requirement;
        }
      }
      ksort($requirements, SORT_STRING);

      $records[] = new RouteComponent(
        routeName: $route_name,
        path: $route->getPath(),
        methods: array_values($route->getMethods()),
        targetType: $target_type,
        target: $target,
        targetClass: $target_class,
        requirements: $requirements,
        adminRoute: $route->getOption('_admin_route') === TRUE,
        sourcePath: $this->sourcePathResolver->resolve($target_class),
      );
    }

    return $records;
  }

  /**
   * Extracts the route execution target.
   *
   * @return array{0: string|null, 1: string|null}
   *   The normalized target type and raw target.
   */
  private function executionTarget(Route $route): array {
    foreach (self::TARGET_DEFAULTS as $default => $target_type) {
      $target = $route->getDefault($default);
      if (is_string($target)) {
        return [$target_type, $target];
      }
    }

    return [NULL, NULL];
  }

  /**
   * Builds a service ID to configured class map.
   *
   * @return array<string, string>
   *   Service classes keyed by service ID.
   */
  private function serviceClasses(): array {
    $classes = [];
    foreach ($this->serviceCollector->collect() as $service) {
      if (!$service instanceof ServiceComponent) {
        throw new \UnexpectedValueException('The service collector must return ServiceComponent objects.');
      }
      if ($service->className !== NULL) {
        $classes[$service->id] = $service->className;
      }
    }

    return $classes;
  }

  /**
   * Builds an entity-type definition map.
   *
   * @return array<string, \Drupal\drupal_developer_assistant\Model\EntityTypeComponent>
   *   Entity type records keyed by entity type ID.
   */
  private function entityTypes(): array {
    $entity_types = [];
    foreach ($this->entityTypeCollector->collect() as $entity_type) {
      if (!$entity_type instanceof EntityTypeComponent) {
        throw new \UnexpectedValueException('The entity type collector must return EntityTypeComponent objects.');
      }
      $entity_types[$entity_type->id] = $entity_type;
    }

    return $entity_types;
  }

  /**
   * Resolves a raw route target to a class name when possible.
   *
   * @param string|null $target_type
   *   The normalized execution target type.
   * @param string|null $target
   *   The raw execution target.
   * @param array<string, string> $service_classes
   *   Service classes keyed by service ID.
   * @param array<string, \Drupal\drupal_developer_assistant\Model\EntityTypeComponent> $entity_types
   *   Entity type records keyed by entity type ID.
   */
  private function resolveTargetClass(
    ?string $target_type,
    ?string $target,
    array $service_classes,
    array $entity_types,
  ): ?string {
    if ($target_type === NULL || $target === NULL) {
      return NULL;
    }

    if ($target_type === 'form') {
      return ltrim($target, '\\');
    }

    if ($target_type === 'controller') {
      return $this->resolveControllerClass($target, $service_classes);
    }

    [$entity_type_id, $operation] = array_pad(
      explode('.', $target, 2),
      2,
      NULL,
    );
    if (!isset($entity_types[$entity_type_id])) {
      return NULL;
    }

    $handlers = $entity_types[$entity_type_id]->handlerClasses;
    return match ($target_type) {
      'entity_form' => $operation === NULL
        ? NULL
        : ($handlers['form.' . $operation] ?? $handlers['form.default'] ?? NULL),
      'entity_list' => $handlers['list_builder'] ?? NULL,
      'entity_view' => $handlers['view_builder'] ?? NULL,
      default => NULL,
    };
  }

  /**
   * Resolves class-based and service-based controller callbacks.
   *
   * @param string $target
   *   The raw controller callback.
   * @param array<string, string> $service_classes
   *   Service classes keyed by service ID.
   */
  private function resolveControllerClass(
    string $target,
    array $service_classes,
  ): ?string {
    if (str_contains($target, '::')) {
      [$candidate] = explode('::', $target, 2);
    }
    elseif (str_contains($target, ':')) {
      [$candidate] = explode(':', $target, 2);
    }
    else {
      $candidate = $target;
    }

    $class_candidate = ltrim($candidate, '\\');
    if (class_exists($class_candidate)) {
      return $class_candidate;
    }

    return $service_classes[$candidate]
      ?? $service_classes[$class_candidate]
      ?? (str_contains($class_candidate, '\\') ? $class_candidate : NULL);
  }

}
