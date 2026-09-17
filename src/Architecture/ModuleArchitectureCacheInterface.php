<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Architecture;

use Drupal\drupal_developer_assistant\Model\ModuleArchitecture;

/**
 * Stores locally discovered module architecture reports.
 */
interface ModuleArchitectureCacheInterface {

  /**
   * Returns the cached architecture for one module, when available.
   */
  public function get(string $module_id): ?ModuleArchitecture;

  /**
   * Stores a discovered architecture report for one module.
   */
  public function set(
    string $module_id,
    ModuleArchitecture $architecture,
  ): void;

  /**
   * Invalidates the cached architecture for one module.
   */
  public function invalidate(string $module_id): void;

}
