<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Model;

/**
 * Contains the components and relationships discovered for one module.
 */
final readonly class ModuleArchitecture {

  /**
   * Constructs a module architecture report.
   *
   * @param \Drupal\drupal_developer_assistant\Model\ModuleComponent $module
   *   The enabled module represented by this report.
   * @param list<\Drupal\drupal_developer_assistant\Model\ModuleArchitectureComponent> $components
   *   Components owned by the module.
   * @param list<\Drupal\drupal_developer_assistant\Model\ComponentRelationship> $relationships
   *   Relationships originating from the module or its components.
   * @param list<\Drupal\drupal_developer_assistant\Model\ModuleSourceFileComponent> $sourceFiles
   *   Files discovered safely inside the module directory.
   * @param list<\Drupal\drupal_developer_assistant\Model\PhpFileAnalysis> $phpFiles
   *   Structural analysis results for the module's PHP files.
   */
  public function __construct(
    public ModuleComponent $module,
    public array $components,
    public array $relationships,
    public array $sourceFiles,
    public array $phpFiles,
  ) {}

}
