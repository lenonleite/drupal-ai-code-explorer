<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Model;

/**
 * Contains structural metadata parsed from one PHP source file.
 */
final readonly class PhpFileAnalysis {

  /**
   * Constructs a PHP file analysis result.
   *
   * @param \Drupal\drupal_developer_assistant\Model\ModuleSourceFileComponent $sourceFile
   *   The source-file metadata represented by this analysis.
   * @param list<string> $namespaces
   *   Namespaces declared by the file.
   * @param array<string, string> $imports
   *   Imported names keyed by alias.
   * @param list<\Drupal\drupal_developer_assistant\Model\PhpSymbol> $symbols
   *   Named class-like declarations.
   * @param list<\Drupal\drupal_developer_assistant\Model\PhpFunction> $functions
   *   Named function declarations.
   * @param string|null $error
   *   A safe parsing or validation error, if analysis was not successful.
   */
  public function __construct(
    public ModuleSourceFileComponent $sourceFile,
    public array $namespaces,
    public array $imports,
    public array $symbols,
    public array $functions,
    public ?string $error,
  ) {}

}
