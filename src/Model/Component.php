<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Model;

/**
 * Represents a component discovered in a Drupal installation.
 */
abstract readonly class Component {

  /**
   * Constructs a discovered component.
   */
  public function __construct(
    public string $id,
    public string $type,
    public string $label,
    public ?string $sourcePath,
  ) {}

}
