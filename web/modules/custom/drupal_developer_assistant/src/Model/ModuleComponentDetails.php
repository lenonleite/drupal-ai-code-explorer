<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Model;

/**
 * Contains one module component and its local architecture context.
 */
final readonly class ModuleComponentDetails {

  /**
   * Constructs component details.
   *
   * @param \Drupal\drupal_developer_assistant\Model\ModuleComponent $module
   *   The enabled module that owns the component.
   * @param \Drupal\drupal_developer_assistant\Model\ModuleArchitectureComponent $component
   *   The selected module-owned component.
   * @param list<\Drupal\drupal_developer_assistant\Model\ComponentRelationship> $incomingRelationships
   *   Relationships whose target is the selected component.
   * @param list<\Drupal\drupal_developer_assistant\Model\ComponentRelationship> $outgoingRelationships
   *   Relationships whose source is the selected component.
   * @param list<\Drupal\drupal_developer_assistant\Model\PhpFileAnalysis> $phpFiles
   *   Parsed PHP files associated with the component source path.
   */
  public function __construct(
    public ModuleComponent $module,
    public ModuleArchitectureComponent $component,
    public array $incomingRelationships,
    public array $outgoingRelationships,
    public array $phpFiles,
  ) {}

}
