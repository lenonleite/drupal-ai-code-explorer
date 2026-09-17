<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Model;

/**
 * Represents an active Drupal configuration object.
 */
final readonly class ConfigurationComponent extends Component {

  /**
   * Constructs a discovered configuration object.
   *
   * @param string $configurationName
   *   The configuration object name.
   * @param string $configurationType
   *   The kind: simple or configuration_entity.
   * @param string|null $entityTypeId
   *   The configuration entity type ID, when applicable.
   * @param string|null $provider
   *   The module providing the configuration entity type, when known.
   * @param string|null $entityTypeClass
   *   The configuration entity implementation class, when applicable.
   * @param list<string> $topLevelKeys
   *   Top-level key names without their configuration values.
   * @param array<string, list<string>> $dependencies
   *   Declared dependencies grouped by dependency type. Nested groups use
   *   dot-separated names, such as "enforced.module".
   * @param string|null $entityTypeSourcePath
   *   The entity type class file relative to the Drupal root, when known.
   */
  public function __construct(
    string $configurationName,
    public string $configurationType,
    public ?string $entityTypeId,
    public ?string $provider,
    public ?string $entityTypeClass,
    public array $topLevelKeys,
    public array $dependencies,
    ?string $entityTypeSourcePath,
  ) {
    parent::__construct(
      id: $configurationName,
      type: 'configuration',
      label: $configurationName,
      sourcePath: $entityTypeSourcePath,
    );
  }

}
