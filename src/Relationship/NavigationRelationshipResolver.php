<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Relationship;

use Drupal\drupal_developer_assistant\Collector\NavigationCollector;
use Drupal\drupal_developer_assistant\Model\ComponentRelationship;
use Drupal\drupal_developer_assistant\Model\NavigationComponent;

/**
 * Resolves YAML navigation definitions into explicit architecture edges.
 */
final class NavigationRelationshipResolver implements RelationshipResolverInterface {

  /**
   * Constructs a navigation relationship resolver.
   */
  public function __construct(
    private readonly NavigationCollector $navigationCollector,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'navigation';
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(): array {
    $components = $this->components();
    $relationships = [];
    $seen = [];

    foreach ($components as $component) {
      $metadata = $this->metadata($component);
      $this->add($relationships, $seen, new ComponentRelationship(
        sourceType: $component->type,
        sourceId: $component->id,
        relationship: 'provided_by',
        targetType: 'module',
        targetId: $component->provider,
        metadata: $metadata,
      ));

      if ($component->routeName !== NULL) {
        $this->add($relationships, $seen, new ComponentRelationship(
          sourceType: $component->type,
          sourceId: $component->id,
          relationship: 'targets',
          targetType: 'route',
          targetId: $component->routeName,
          metadata: $metadata,
        ));
      }

      if ($component->parentId !== NULL) {
        $this->add($relationships, $seen, new ComponentRelationship(
          sourceType: $component->type,
          sourceId: $component->id,
          relationship: 'child_of',
          targetType: $component->type,
          targetId: $component->parentId,
          metadata: $metadata,
        ));
        $parent = $components[$this->key(
          $component->type,
          $component->parentId,
        )] ?? NULL;
        if ($parent !== NULL) {
          $this->addNavigationTransition(
            $relationships,
            $seen,
            $parent->routeName,
            $component->routeName,
            $component,
            'parent',
          );
        }
      }

      if ($component->baseRoute !== NULL) {
        $this->add($relationships, $seen, new ComponentRelationship(
          sourceType: $component->type,
          sourceId: $component->id,
          relationship: 'based_on',
          targetType: 'route',
          targetId: $component->baseRoute,
          metadata: $metadata,
        ));
        $this->addNavigationTransition(
          $relationships,
          $seen,
          $component->baseRoute,
          $component->routeName,
          $component,
          'base_route',
        );
      }

      foreach ($component->appearsOn as $route_name) {
        $this->add($relationships, $seen, new ComponentRelationship(
          sourceType: $component->type,
          sourceId: $component->id,
          relationship: 'appears_on',
          targetType: 'route',
          targetId: $route_name,
          metadata: $metadata,
        ));
        $this->addNavigationTransition(
          $relationships,
          $seen,
          $route_name,
          $component->routeName,
          $component,
          'appears_on',
        );
      }
    }

    return $relationships;
  }

  /**
   * Collects typed navigation components keyed by type and definition ID.
   *
   * @return array<string, \Drupal\drupal_developer_assistant\Model\NavigationComponent>
   *   Navigation components keyed by a collision-safe key.
   */
  private function components(): array {
    $components = [];
    foreach ($this->navigationCollector->collect() as $component) {
      if (!$component instanceof NavigationComponent) {
        throw new \UnexpectedValueException('The navigation collector must return NavigationComponent objects.');
      }
      $components[$this->key($component->type, $component->id)] = $component;
    }

    return $components;
  }

  /**
   * Adds a proven route-to-route transition without duplicate edges.
   */
  private function addNavigationTransition(
    array &$relationships,
    array &$seen,
    ?string $source_route,
    ?string $target_route,
    NavigationComponent $component,
    string $via,
  ): void {
    if (
      $source_route === NULL
      || $target_route === NULL
      || $source_route === $target_route
    ) {
      return;
    }

    $this->add($relationships, $seen, new ComponentRelationship(
      sourceType: 'route',
      sourceId: $source_route,
      relationship: 'navigates_to',
      targetType: 'route',
      targetId: $target_route,
      metadata: array_merge($this->metadata($component), [
        'navigation_id' => $component->id,
        'via' => $via,
      ]),
    ));
  }

  /**
   * Returns common evidence metadata for a navigation definition.
   *
   * @return array<string, string>
   *   Safe relationship metadata.
   */
  private function metadata(NavigationComponent $component): array {
    return [
      'navigation_type' => $component->navigationType,
      'source_path' => $component->sourcePath ?? '',
    ];
  }

  /**
   * Adds one unique relationship while preserving discovery order.
   */
  private function add(
    array &$relationships,
    array &$seen,
    ComponentRelationship $relationship,
  ): void {
    $key = implode("\0", [
      $relationship->sourceType,
      $relationship->sourceId,
      $relationship->relationship,
      $relationship->targetType,
      $relationship->targetId,
    ]);
    if (isset($seen[$key])) {
      return;
    }

    $seen[$key] = TRUE;
    $relationships[] = $relationship;
  }

  /**
   * Builds a collision-safe component key.
   */
  private function key(string $type, string $id): string {
    return $type . "\0" . $id;
  }

}
