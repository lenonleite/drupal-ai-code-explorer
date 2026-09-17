<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Collector;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\drupal_developer_assistant\Model\ConfigurationComponent;
use Drupal\drupal_developer_assistant\Model\EntityTypeComponent;

/**
 * Collects metadata from Drupal's active configuration store.
 */
final class ConfigurationCollector implements CollectorInterface {

  /**
   * Constructs a configuration collector.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ConfigManagerInterface $configManager,
    private readonly EntityTypeCollector $entityTypeCollector,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'configuration';
  }

  /**
   * {@inheritdoc}
   */
  public function collect(): array {
    $names = $this->configFactory->listAll();
    if (!array_is_list($names) || array_filter($names, 'is_string') !== $names) {
      throw new \UnexpectedValueException('The configuration factory must return a list of configuration names.');
    }
    sort($names, SORT_STRING);

    $configurations = $this->configFactory->loadMultiple($names);
    $entity_types = $this->entityTypes();
    $records = [];

    foreach ($names as $name) {
      $configuration = $configurations[$name] ?? NULL;
      if (!$configuration instanceof ImmutableConfig) {
        throw new \UnexpectedValueException('The configuration factory must load immutable configuration objects.');
      }

      $data = $configuration->getRawData();
      $top_level_keys = array_values(array_filter(
        array_keys($data),
        'is_string',
      ));
      sort($top_level_keys, SORT_STRING);

      $entity_type_id = $this->configManager->getEntityTypeIdByName($name);
      if ($entity_type_id !== NULL && !is_string($entity_type_id)) {
        throw new \UnexpectedValueException('Configuration entity type IDs must be strings or NULL.');
      }
      $entity_type = $entity_type_id === NULL
        ? NULL
        : ($entity_types[$entity_type_id] ?? NULL);

      $records[] = new ConfigurationComponent(
        configurationName: $name,
        configurationType: $entity_type_id === NULL
          ? 'simple'
          : 'configuration_entity',
        entityTypeId: $entity_type_id,
        provider: $entity_type?->provider,
        entityTypeClass: $entity_type?->entityClass,
        topLevelKeys: $top_level_keys,
        dependencies: $this->normalizeDependencies(
          $data['dependencies'] ?? [],
        ),
        entityTypeSourcePath: $entity_type?->sourcePath,
      );
    }

    return $records;
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
   * Normalizes declared configuration dependencies.
   *
   * @param mixed $dependencies
   *   Raw dependency data from the configuration object.
   *
   * @return array<string, list<string>>
   *   String dependency names grouped by dependency type.
   */
  private function normalizeDependencies(mixed $dependencies): array {
    if (!is_array($dependencies)) {
      return [];
    }

    $normalized = [];
    $this->flattenDependencies($dependencies, $normalized);
    ksort($normalized, SORT_STRING);

    return $normalized;
  }

  /**
   * Flattens nested dependency groups using dot-separated type names.
   *
   * Drupal stores enforced dependencies below an additional "enforced" key.
   * For example, an enforced module dependency becomes "enforced.module".
   *
   * @param array<mixed> $dependencies
   *   Dependency data at the current nesting level.
   * @param array<string, list<string>> $normalized
   *   The normalized dependency groups, passed by reference.
   * @param string $prefix
   *   The type prefix accumulated from parent groups.
   */
  private function flattenDependencies(
    array $dependencies,
    array &$normalized,
    string $prefix = '',
  ): void {
    foreach ($dependencies as $dependency_type => $names) {
      if (!is_string($dependency_type) || !is_array($names)) {
        continue;
      }

      $qualified_type = $prefix === ''
        ? $dependency_type
        : $prefix . '.' . $dependency_type;
      $dependency_names = array_values(array_filter($names, 'is_string'));
      sort($dependency_names, SORT_STRING);
      if ($dependency_names !== []) {
        $normalized[$qualified_type] = $dependency_names;
      }

      $this->flattenDependencies($names, $normalized, $qualified_type);
    }
  }

}
