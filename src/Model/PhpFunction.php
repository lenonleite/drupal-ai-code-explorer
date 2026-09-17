<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Model;

/**
 * Represents a named PHP function declared in a source file.
 */
final readonly class PhpFunction {

  /**
   * Constructs a PHP function description.
   *
   * @param string $name
   *   The short function name.
   * @param string $fullyQualifiedName
   *   The namespace-qualified function name.
   * @param bool $returnsByReference
   *   Whether the function returns by reference.
   * @param string|null $returnType
   *   The fully resolved declared return type, when present.
   * @param list<\Drupal\drupal_developer_assistant\Model\PhpParameter> $parameters
   *   Function parameters in declaration order.
   * @param list<string> $attributes
   *   Fully resolved PHP attribute class names.
   * @param int|null $startLine
   *   The function's first source line, when known.
   * @param int|null $endLine
   *   The function's last source line, when known.
   */
  public function __construct(
    public string $name,
    public string $fullyQualifiedName,
    public bool $returnsByReference,
    public ?string $returnType,
    public array $parameters,
    public array $attributes,
    public ?int $startLine = NULL,
    public ?int $endLine = NULL,
  ) {}

}
