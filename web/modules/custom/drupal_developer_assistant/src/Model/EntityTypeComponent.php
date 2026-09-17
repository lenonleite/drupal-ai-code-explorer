<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Model;

/**
 * Represents a Drupal entity-type definition.
 */
final readonly class EntityTypeComponent extends Component {

  /**
   * Constructs a discovered entity type.
   *
   * @param string $entityTypeId
   *   The entity type identifier.
   * @param string $label
   *   The human-readable entity type label.
   * @param string $entityKind
   *   The entity kind: content, configuration, or other.
   * @param string|null $entityClass
   *   The entity implementation class, if known.
   * @param string|null $provider
   *   The module that provides the entity type, if known.
   * @param string|null $baseTable
   *   The content entity's base database table, if any.
   * @param string|null $configPrefix
   *   The configuration entity's configuration prefix, if any.
   * @param array<string, string> $handlerClasses
   *   Handler classes keyed by flattened handler identifiers.
   * @param string|null $sourcePath
   *   The entity class file relative to the Drupal root, if known.
   */
  public function __construct(
    string $entityTypeId,
    string $label,
    public string $entityKind,
    public ?string $entityClass,
    public ?string $provider,
    public ?string $baseTable,
    public ?string $configPrefix,
    public array $handlerClasses,
    ?string $sourcePath,
  ) {
    parent::__construct(
      id: $entityTypeId,
      type: 'entity_type',
      label: $label,
      sourcePath: $sourcePath,
    );
  }

}
