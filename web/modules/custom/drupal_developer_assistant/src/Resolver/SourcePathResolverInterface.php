<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Resolver;

/**
 * Resolves PHP symbols to source-file paths.
 */
interface SourcePathResolverInterface {

  /**
   * Resolves a class to its implementation file.
   *
   * @param string|null $class_name
   *   The class to inspect.
   *
   * @return string|null
   *   A path relative to the Drupal root, or NULL when it cannot be found.
   */
  public function resolve(?string $class_name): ?string;

  /**
   * Resolves a function to its implementation file.
   *
   * @param string|null $function_name
   *   The function to inspect.
   *
   * @return string|null
   *   A path relative to the Drupal root, or NULL when it cannot be found.
   */
  public function resolveFunction(?string $function_name): ?string;

}
