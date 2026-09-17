<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Model;

/**
 * Represents a directed relationship between two discovered components.
 */
final readonly class ComponentRelationship {

  /**
   * Constructs a component relationship.
   *
   * @param string $sourceType
   *   The source component type.
   * @param string $sourceId
   *   The source component identifier.
   * @param string $relationship
   *   The relationship verb.
   * @param string $targetType
   *   The target component type.
   * @param string $targetId
   *   The target component identifier.
   * @param array<string, string> $metadata
   *   Additional non-sensitive relationship details.
   */
  public function __construct(
    public string $sourceType,
    public string $sourceId,
    public string $relationship,
    public string $targetType,
    public string $targetId,
    public array $metadata = [],
  ) {}

}
