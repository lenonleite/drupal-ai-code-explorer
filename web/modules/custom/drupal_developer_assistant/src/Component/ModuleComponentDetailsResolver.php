<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Component;

use Drupal\drupal_developer_assistant\Architecture\ModuleArchitectureBuilderInterface;
use Drupal\drupal_developer_assistant\Model\ModuleComponentDetails;

/**
 * Resolves discovered components without exposing raw IDs in route parameters.
 */
final readonly class ModuleComponentDetailsResolver implements ModuleComponentDetailsResolverInterface {

  /**
   * Constructs a component-details resolver.
   */
  public function __construct(
    private ModuleArchitectureBuilderInterface $architectureBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function referenceId(
    string $module_id,
    string $component_type,
    string $component_id,
  ): string {
    return hash('sha256', implode("\0", [
      'module-component-v1',
      $module_id,
      $component_type,
      $component_id,
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(
    string $module_id,
    string $component_reference,
  ): ?ModuleComponentDetails {
    if (!preg_match('/\A[a-f0-9]{64}\z/', $component_reference)) {
      return NULL;
    }

    $architecture = $this->architectureBuilder->build($module_id);
    if ($architecture === NULL) {
      return NULL;
    }

    foreach ($architecture->components as $component) {
      if (!hash_equals(
        $this->referenceId($module_id, $component->type, $component->id),
        $component_reference,
      )) {
        continue;
      }

      $incoming = [];
      $outgoing = [];
      foreach ($architecture->relationships as $relationship) {
        if (
          $relationship->sourceType === $component->type
          && $relationship->sourceId === $component->id
        ) {
          $outgoing[] = $relationship;
        }
        if (
          $relationship->targetType === $component->type
          && $relationship->targetId === $component->id
        ) {
          $incoming[] = $relationship;
        }
      }

      $php_files = [];
      if ($component->sourcePath !== NULL) {
        foreach ($architecture->phpFiles as $php_file) {
          if ($php_file->sourceFile->sourcePath === $component->sourcePath) {
            $php_files[] = $php_file;
          }
        }
      }

      return new ModuleComponentDetails(
        module: $architecture->module,
        component: $component,
        incomingRelationships: $incoming,
        outgoingRelationships: $outgoing,
        phpFiles: $php_files,
      );
    }

    return NULL;
  }

}
