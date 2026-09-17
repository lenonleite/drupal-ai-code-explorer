<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Evidence;

use Drupal\drupal_developer_assistant\Model\ModuleArchitecture;

/**
 * Creates opaque citation IDs and safely resolves them for one module.
 */
interface ModuleEvidenceResolverInterface {

  /**
   * Creates a deterministic opaque ID for one exact evidence reference.
   */
  public function referenceId(
    string $module_id,
    string $source_path,
    string $symbol,
  ): string;

  /**
   * Resolves an ID only when it matches discovered module evidence.
   */
  public function resolve(
    ModuleArchitecture $architecture,
    string $evidence_id,
  ): ?ModuleSourceEvidence;

}
