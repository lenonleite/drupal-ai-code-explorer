<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Model;

/**
 * Represents a property declared directly by a PHP class-like symbol.
 */
final readonly class PhpProperty {

  /**
   * Constructs a PHP property description.
   *
   * @param string $name
   *   The property name without the dollar-sign prefix.
   * @param string $visibility
   *   The declared public, protected, or private visibility.
   * @param string|null $type
   *   The fully resolved declared type, when present.
   * @param bool $static
   *   Whether the property is static.
   * @param bool $readonly
   *   Whether the property is readonly.
   * @param list<string> $attributes
   *   Fully resolved PHP attribute class names.
   */
  public function __construct(
    public string $name,
    public string $visibility,
    public ?string $type,
    public bool $static,
    public bool $readonly,
    public array $attributes,
  ) {}

}
