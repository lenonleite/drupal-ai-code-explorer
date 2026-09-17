<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Model;

/**
 * Represents a named PHP class, interface, trait, or enum declaration.
 */
final readonly class PhpSymbol {

  /**
   * Constructs a PHP symbol description.
   *
   * @param string $kind
   *   The declaration kind: class, interface, trait, or enum.
   * @param string $name
   *   The short symbol name.
   * @param string $fullyQualifiedName
   *   The namespace-qualified symbol name.
   * @param list<string> $modifiers
   *   Class modifiers such as abstract, final, or readonly.
   * @param list<string> $extends
   *   Fully resolved parent class or interface names.
   * @param list<string> $implements
   *   Fully resolved implemented interface names.
   * @param list<string> $traits
   *   Fully resolved directly used trait names.
   * @param list<string> $attributes
   *   Fully resolved PHP attribute class names.
   * @param list<\Drupal\drupal_developer_assistant\Model\PhpProperty> $properties
   *   Properties declared directly by the symbol.
   * @param list<\Drupal\drupal_developer_assistant\Model\PhpMethod> $methods
   *   Methods declared directly by the symbol.
   * @param int|null $startLine
   *   The declaration's first source line, when known.
   * @param int|null $endLine
   *   The declaration's last source line, when known.
   */
  public function __construct(
    public string $kind,
    public string $name,
    public string $fullyQualifiedName,
    public array $modifiers,
    public array $extends,
    public array $implements,
    public array $traits,
    public array $attributes,
    public array $properties,
    public array $methods,
    public ?int $startLine = NULL,
    public ?int $endLine = NULL,
  ) {}

}
