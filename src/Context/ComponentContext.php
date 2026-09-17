<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Context;

/**
 * Defines the provider-independent context for one Drupal component.
 */
final readonly class ComponentContext implements \JsonSerializable {

  /**
   * The component-context contract version.
   */
  public const SCHEMA_VERSION = '1.1';

  /**
   * Constructs a component context document.
   *
   * @param array<string, mixed> $module
   *   Normalized module identity and metadata.
   * @param array<string, mixed> $component
   *   The selected normalized component.
   * @param array<string, mixed> $summary
   *   Included evidence counts and limits.
   * @param list<array<string, mixed>> $outgoingRelationships
   *   Relationships originating from the component.
   * @param list<array<string, mixed>> $incomingRelationships
   *   Relationships targeting the component.
   * @param list<array<string, mixed>> $phpStructure
   *   Bounded PHP structure associated with the component.
   */
  public function __construct(
    public array $module,
    public array $component,
    public array $summary,
    public array $outgoingRelationships,
    public array $incomingRelationships,
    public array $phpStructure,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function jsonSerialize(): array {
    return [
      'schema_version' => self::SCHEMA_VERSION,
      'module' => $this->module,
      'component' => $this->component,
      'summary' => $this->summary,
      'outgoing_relationships' => $this->outgoingRelationships,
      'incoming_relationships' => $this->incomingRelationships,
      'php_structure' => $this->phpStructure,
    ];
  }

}
