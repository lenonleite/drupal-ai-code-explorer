<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Analyzer;

use Drupal\drupal_developer_assistant\Model\ModuleComponent;
use Drupal\drupal_developer_assistant\Model\ModuleSourceFileComponent;
use Drupal\drupal_developer_assistant\Model\PhpFileAnalysis;

/**
 * Statically analyzes structural metadata from one module PHP file.
 */
interface PhpSourceAnalyzerInterface {

  /**
   * Analyzes a PHP file without loading or executing it.
   */
  public function analyze(
    ModuleComponent $module,
    ModuleSourceFileComponent $source_file,
  ): PhpFileAnalysis;

}
