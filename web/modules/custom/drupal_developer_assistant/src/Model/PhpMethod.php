<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Model;

/**
 * Represents a method declared directly by a PHP class-like symbol.
 */
final readonly class PhpMethod {

  /**
   * Constructs a PHP method description.
   *
   * @param string $name
   *   The method name.
   * @param string $visibility
   *   The declared public, protected, or private visibility.
   * @param bool $static
   *   Whether the method is static.
   * @param bool $abstract
   *   Whether the method is abstract.
   * @param bool $final
   *   Whether the method is final.
   * @param bool $returnsByReference
   *   Whether the method returns by reference.
   * @param string|null $returnType
   *   The fully resolved declared return type, when present.
   * @param list<\Drupal\drupal_developer_assistant\Model\PhpParameter> $parameters
   *   Method parameters in declaration order.
   * @param list<string> $attributes
   *   Fully resolved PHP attribute class names.
   * @param int|null $startLine
   *   The method's first source line, when known.
   * @param int|null $endLine
   *   The method's last source line, when known.
   */
  public function __construct(
    public string $name,
    public string $visibility,
    public bool $static,
    public bool $abstract,
    public bool $final,
    public bool $returnsByReference,
    public ?string $returnType,
    public array $parameters,
    public array $attributes,
    public ?int $startLine = NULL,
    public ?int $endLine = NULL,
  ) {}

}
