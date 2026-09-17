<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Collector;

use Drupal\drupal_developer_assistant\Model\ModuleComponent;

/**
 * Collects source-file metadata for one enabled module.
 */
interface ModuleSourceFileCollectorInterface {

  /**
   * Collects files located safely inside the module directory.
   *
   * @param \Drupal\drupal_developer_assistant\Model\ModuleComponent $module
   *   The module whose files should be inspected.
   *
   * @return list<\Drupal\drupal_developer_assistant\Model\ModuleSourceFileComponent>
   *   Source-file metadata sorted by relative path.
   */
  public function collect(ModuleComponent $module): array;

}
