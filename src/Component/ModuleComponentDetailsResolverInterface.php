<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Component;

use Drupal\drupal_developer_assistant\Model\ModuleComponentDetails;

/**
 * Resolves opaque references to discovered module components.
 */
interface ModuleComponentDetailsResolverInterface {

  /**
   * Creates a stable opaque reference for a module component.
   */
  public function referenceId(
    string $module_id,
    string $component_type,
    string $component_id,
  ): string;

  /**
   * Resolves one known module component and its relationships.
   */
  public function resolve(
    string $module_id,
    string $component_reference,
  ): ?ModuleComponentDetails;

}
