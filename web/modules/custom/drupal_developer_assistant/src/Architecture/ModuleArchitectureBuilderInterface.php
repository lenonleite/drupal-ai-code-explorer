<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Architecture;

use Drupal\drupal_developer_assistant\Model\ModuleArchitecture;

/**
 * Builds a component and dependency report for an enabled module.
 */
interface ModuleArchitectureBuilderInterface {

  /**
   * Builds the architecture report for an enabled module.
   *
   * @param string $module_id
   *   The module machine name.
   *
   * @return \Drupal\drupal_developer_assistant\Model\ModuleArchitecture|null
   *   The architecture report, or NULL when the module is not enabled.
   */
  public function build(string $module_id): ?ModuleArchitecture;

}
