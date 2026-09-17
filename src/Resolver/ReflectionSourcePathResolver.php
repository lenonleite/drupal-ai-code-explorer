<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Resolver;

use Composer\Autoload\ClassLoader;

/**
 * Resolves PHP symbol source paths without autoloading inspected classes.
 */
final readonly class ReflectionSourcePathResolver implements SourcePathResolverInterface {

  /**
   * Constructs a reflection source-path resolver.
   */
  public function __construct(
    private string $appRoot,
    private ClassLoader $classLoader,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function resolve(?string $class_name): ?string {
    if ($class_name === NULL) {
      return NULL;
    }

    $class_name = ltrim($class_name, '\\');
    try {
      if ($this->isLoaded($class_name)) {
        $filename = (new \ReflectionClass($class_name))->getFileName();
      }
      else {
        $filename = $this->classLoader->findFile($class_name);
      }
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
   * Determines whether a class-like symbol is already loaded.
   */
  private function isLoaded(string $class_name): bool {
    return class_exists($class_name, FALSE)
      || interface_exists($class_name, FALSE)
      || trait_exists($class_name, FALSE)
      || enum_exists($class_name, FALSE);
  }

  /**
   * Makes a source filename relative to the Drupal root when it is contained.
   */
  private function normalizePath(string $filename): ?string {
    $app_root = realpath($this->appRoot);
    $source_path = realpath($filename);
    if (
      $app_root === FALSE
      || $source_path === FALSE
      || !$this->isInside($source_path, $app_root)
    ) {
      return NULL;
    }

    $relative_path = ltrim(
      substr($source_path, strlen($app_root)),
      DIRECTORY_SEPARATOR,
    );
    return str_replace(DIRECTORY_SEPARATOR, '/', $relative_path);
  }

  /**
   * Determines whether a canonical path is inside a canonical directory.
   */
  private function isInside(string $path, string $directory): bool {
    return $path === $directory
      || str_starts_with($path, $directory . DIRECTORY_SEPARATOR);
  }

}
