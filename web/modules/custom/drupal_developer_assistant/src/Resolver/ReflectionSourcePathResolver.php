<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Resolver;

/**
 * Resolves PHP symbol source paths using reflection.
 */
final readonly class ReflectionSourcePathResolver implements SourcePathResolverInterface {

  /**
   * Constructs a reflection source-path resolver.
   */
  public function __construct(
    private string $appRoot,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function resolve(?string $class_name): ?string {
    if ($class_name === NULL) {
      return NULL;
    }

    try {
      if (!class_exists($class_name)) {
        return NULL;
      }
      $filename = (new \ReflectionClass($class_name))->getFileName();
    }
    catch (\Throwable) {
      return NULL;
    }
    if ($filename === FALSE) {
      return NULL;
    }

    return $this->normalizePath($filename);
  }

  /**
   * {@inheritdoc}
   */
  public function resolveFunction(?string $function_name): ?string {
    if ($function_name === NULL || !function_exists($function_name)) {
      return NULL;
    }

    $filename = (new \ReflectionFunction($function_name))->getFileName();
    if ($filename === FALSE) {
      return NULL;
    }

    return $this->normalizePath($filename);
  }

  /**
   * Makes a reflected filename relative to the Drupal root when possible.
   */
  private function normalizePath(string $filename): string {
    $app_root = rtrim($this->appRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return str_starts_with($filename, $app_root)
      ? substr($filename, strlen($app_root))
      : $filename;
  }

}
