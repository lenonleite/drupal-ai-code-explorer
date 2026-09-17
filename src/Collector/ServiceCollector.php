<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Collector;

use Drupal\Core\DrupalKernelInterface;
use Drupal\drupal_developer_assistant\Model\ServiceComponent;

/**
 * Collects service definitions from Drupal's compiled container cache.
 */
final class ServiceCollector implements CollectorInterface {

  /**
   * Constructs a service collector.
   */
  public function __construct(
    private readonly DrupalKernelInterface $kernel,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'services';
  }

  /**
   * {@inheritdoc}
   */
  public function collect(): array {
    $container_definition = $this->kernel->getCachedContainerDefinition();
    if ($container_definition === NULL) {
      return [];
    }

    $definitions = $container_definition['services'] ?? [];
    $aliases = $container_definition['aliases'] ?? [];
    $service_ids = array_unique([
      ...array_keys($definitions),
      ...array_keys($aliases),
    ]);
    sort($service_ids, SORT_STRING);

    $records = [];
    foreach ($service_ids as $service_id) {
      $alias_target = $aliases[$service_id] ?? NULL;
      $target_id = $alias_target ?? $service_id;

      $records[] = new ServiceComponent(
        id: $service_id,
        className: $this->resolveClassName($target_id, $definitions, $aliases),
        aliasTarget: $alias_target,
        references: $alias_target === NULL
          ? $this->collectReferences($definitions[$service_id] ?? NULL)
          : [],
      );
    }

    return $records;
  }

  /**
   * Resolves the configured class for a service or an alias chain.
   *
   * @param string $service_id
   *   The service identifier to resolve.
   * @param array<string, mixed> $definitions
   *   The compiled service definitions.
   * @param array<string, string> $aliases
   *   The compiled service aliases.
   *
   * @return string|null
   *   The configured class name, if one exists.
   */
  private function resolveClassName(
    string $service_id,
    array $definitions,
    array $aliases,
  ): ?string {
    $visited = [];
    while (isset($aliases[$service_id])) {
      if (isset($visited[$service_id])) {
        return NULL;
      }

      $visited[$service_id] = TRUE;
      $service_id = $aliases[$service_id];
    }

    if (!isset($definitions[$service_id])) {
      return NULL;
    }

    $definition = $this->decodeDefinition($definitions[$service_id]);
    if ($definition === NULL || !isset($definition['class'])) {
      return NULL;
    }

    return is_string($definition['class']) ? $definition['class'] : NULL;
  }

  /**
   * Collects direct service references from a compiled definition.
   *
   * @param mixed $definition
   *   A serialized or decoded service definition.
   *
   * @return array<string, list<string>>
   *   Referenced service IDs grouped by definition context.
   */
  private function collectReferences(mixed $definition): array {
    $definition = $this->decodeDefinition($definition);
    if ($definition === NULL) {
      return [];
    }

    $contexts = [
      'constructor' => 'arguments',
      'property' => 'properties',
      'method_call' => 'calls',
      'factory' => 'factory',
      'configurator' => 'configurator',
    ];
    $references = [];
    foreach ($contexts as $context => $definition_key) {
      if (!array_key_exists($definition_key, $definition)) {
        continue;
      }

      $service_ids = array_values(array_unique(
        $this->findServiceReferences($definition[$definition_key]),
      ));
      sort($service_ids, SORT_STRING);
      if ($service_ids !== []) {
        $references[$context] = $service_ids;
      }
    }

    return $references;
  }

  /**
   * Finds direct service-reference objects in a compiled value.
   *
   * @return list<string>
   *   Referenced service IDs.
   */
  private function findServiceReferences(mixed $value): array {
    if ($value instanceof \stdClass) {
      $properties = get_object_vars($value);
      $type = $properties['type'] ?? NULL;
      if (
        in_array($type, ['service', 'service_closure', 'private_service'], TRUE)
        && is_string($properties['id'] ?? NULL)
      ) {
        return [$properties['id']];
      }

      if (
        in_array($type, ['collection', 'iterator'], TRUE)
        && array_key_exists('value', $properties)
      ) {
        return $this->findServiceReferences($properties['value']);
      }

      return [];
    }

    if (!is_array($value)) {
      return [];
    }

    $references = [];
    foreach ($value as $item) {
      $references = [
        ...$references,
        ...$this->findServiceReferences($item),
      ];
    }

    return $references;
  }

  /**
   * Decodes a service definition from Drupal's optimized container format.
   *
   * @return array<string, mixed>|null
   *   The decoded definition, or NULL when it is unavailable or malformed.
   */
  private function decodeDefinition(mixed $definition): ?array {
    if (is_string($definition)) {
      $definition = unserialize(
        $definition,
        ['allowed_classes' => [\stdClass::class]],
      );
    }

    return is_array($definition) ? $definition : NULL;
  }

}
