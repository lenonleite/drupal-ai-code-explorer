<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Relationship;

/**
 * Resolves service dependencies declared by analyzed PHP constructors.
 */
interface PhpDependencyResolverInterface {

  /**
   * Resolves constructor parameter types to Drupal services.
   *
   * @param list<\Drupal\drupal_developer_assistant\Model\PhpFileAnalysis> $php_files
   *   Module PHP files that were analyzed successfully or unsuccessfully.
   * @param list<\Drupal\drupal_developer_assistant\Model\ServiceComponent> $services
   *   Services collected from Drupal's compiled container.
   *
   * @return list<\Drupal\drupal_developer_assistant\Model\ComponentRelationship>
   *   Conservative class-to-service dependency relationships.
   */
  public function resolve(array $php_files, array $services): array;

}
