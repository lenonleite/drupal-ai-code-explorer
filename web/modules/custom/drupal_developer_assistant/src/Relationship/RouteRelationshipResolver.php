<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Relationship;

use Drupal\drupal_developer_assistant\Collector\RouteCollector;
use Drupal\drupal_developer_assistant\Model\ComponentRelationship;
use Drupal\drupal_developer_assistant\Model\RouteComponent;

/**
 * Resolves route-to-controller, form, and permission relationships.
 */
final class RouteRelationshipResolver implements RelationshipResolverInterface {

  /**
   * Constructs a route relationship resolver.
   */
  public function __construct(
    private readonly RouteCollector $routeCollector,
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
  public function resolve(): array {
    $relationships = [];
    foreach ($this->routeCollector->collect() as $route) {
      if (!$route instanceof RouteComponent) {
        throw new \UnexpectedValueException('The route collector must return RouteComponent objects.');
      }

      $target_relationship = $this->targetRelationship($route);
      if ($target_relationship !== NULL) {
        $relationships[] = $target_relationship;
      }

      $permission = $route->requirements['_permission'] ?? NULL;
      if ($permission !== NULL) {
        $relationships[] = new ComponentRelationship(
          sourceType: 'route',
          sourceId: $route->id,
          relationship: 'requires',
          targetType: 'permission',
          targetId: $permission,
          metadata: ['path' => $route->label],
        );
      }
    }

    return $relationships;
  }

  /**
   * Resolves the route's executable controller or form target.
   */
  private function targetRelationship(
    RouteComponent $route,
  ): ?ComponentRelationship {
    if (
      $route->target === NULL
      || $route->targetClass === NULL
    ) {
      return NULL;
    }

    if ($route->targetType === 'controller') {
      return new ComponentRelationship(
        sourceType: 'route',
        sourceId: $route->id,
        relationship: 'invokes',
        targetType: 'controller',
        targetId: $route->targetClass,
        metadata: [
          'method' => $this->callbackMethod($route->target),
          'path' => $route->label,
          'raw_target' => $route->target,
        ],
      );
    }

    if (in_array($route->targetType, ['form', 'entity_form'], TRUE)) {
      return new ComponentRelationship(
        sourceType: 'route',
        sourceId: $route->id,
        relationship: 'builds',
        targetType: 'form',
        targetId: $route->targetClass,
        metadata: [
          'path' => $route->label,
          'raw_target' => $route->target,
          'route_target_type' => $route->targetType,
        ],
      );
    }

    return NULL;
  }

  /**
   * Extracts the callback method from a controller route target.
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

}
