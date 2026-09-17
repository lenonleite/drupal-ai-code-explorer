<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Context;

/**
 * Defines the provider-independent context contract for one Drupal module.
 */
final readonly class ModuleContext implements \JsonSerializable {

  /**
   * The contract version consumed by later prompt and retrieval layers.
   */
  public const SCHEMA_VERSION = '1.3';

  /**
   * Constructs a module context document.
   *
   * @param array{id: string, name: string, path: string|null, package: string|null, version: string|null, dependencies: list<string>} $module
   *   Safe module identity and package metadata.
   * @param array<string, mixed> $summary
   *   Available, included, and omitted counts for the context document.
   * @param list<array<string, mixed>> $components
   *   Normalized module-owned components.
   * @param list<array<string, mixed>> $phpStructure
   *   Normalized structural facts parsed from PHP files.
   * @param list<array<string, mixed>> $relationships
   *   Normalized architectural relationships.
   */
  public function __construct(
    public array $module,
    public array $summary,
    public array $components,
    public array $phpStructure,
    public array $relationships,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function jsonSerialize(): array {
    return [
      'schema_version' => self::SCHEMA_VERSION,
      'module' => $this->module,
      'summary' => $this->summary,
      'components' => $this->components,
      'php_structure' => $this->phpStructure,
      'relationships' => $this->relationships,
    ];
  }

}
