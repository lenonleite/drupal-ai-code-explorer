<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Context;

/**
 * Defines structural limits for provider-independent module context.
 */
final readonly class ModuleContextLimits {

  /**
   * Constructs a module context limit set.
   */
  public function __construct(
    public int $maxArchitectureComponents = 100,
    public int $maxSourceFiles = 150,
    public int $maxPhpFiles = 30,
    public int $maxSymbolsPerFile = 10,
    public int $maxPropertiesPerSymbol = 25,
    public int $maxMethodsPerSymbol = 25,
    public int $maxFunctionsPerFile = 20,
    public int $maxParametersPerCallable = 20,
    public int $maxRelationships = 200,
    public int $maxStringBytes = 500,
  ) {
    foreach (get_object_vars($this) as $name => $value) {
      if ($value < 0) {
        throw new \InvalidArgumentException(sprintf(
          'Context limit "%s" cannot be negative.',
          $name,
        ));
      }
    }
    if ($this->maxStringBytes < 4) {
      throw new \InvalidArgumentException('The string limit must be at least four bytes.');
    }
  }

}
