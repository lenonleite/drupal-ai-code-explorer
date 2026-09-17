<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Context;

use Drupal\drupal_developer_assistant\Model\ModuleArchitecture;

/**
 * Builds provider-independent context from a module architecture report.
 */
interface ModuleContextBuilderInterface {

  /**
   * Converts an architecture report into a JSON-ready context document.
   */
  public function build(ModuleArchitecture $architecture): ModuleContext;

}
