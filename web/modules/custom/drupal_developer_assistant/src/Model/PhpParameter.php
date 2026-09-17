<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Model;

/**
 * Represents a parameter declared by a PHP method or function.
 */
final readonly class PhpParameter {

  /**
   * Constructs a PHP parameter description.
   *
   * @param string $name
   *   The parameter name without the dollar-sign prefix.
   * @param string|null $type
   *   The fully resolved declared type, when present.
   * @param bool $byReference
   *   Whether the parameter is passed by reference.
   * @param bool $variadic
   *   Whether the parameter accepts variadic arguments.
   * @param bool $promoted
   *   Whether the parameter promotes a constructor property.
   * @param bool $hasDefault
   *   Whether a default exists, without retaining its value.
   * @param list<string> $attributes
   *   Fully resolved PHP attribute class names.
   */
  public function __construct(
    public string $name,
    public ?string $type,
    public bool $byReference,
    public bool $variadic,
    public bool $promoted,
    public bool $hasDefault,
    public array $attributes,
  ) {}

}
