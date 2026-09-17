<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Model;

/**
 * Represents a component owned by one module in an architecture report.
 */
final readonly class ModuleArchitectureComponent {

  /**
   * Constructs a module-owned architecture component.
   */
  public function __construct(
    public string $type,
    public string $id,
    public string $label,
    public ?string $sourcePath,
  ) {}

}
