<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Collector;

use Drupal\drupal_developer_assistant\Model\ModuleComponent;
use Drupal\drupal_developer_assistant\Model\ModuleSourceFileComponent;

/**
 * Collects non-sensitive filesystem metadata from one module directory.
 */
final class ModuleSourceFileCollector implements ModuleSourceFileCollectorInterface {

  /**
   * Directory names excluded from recursive module scans.
   */
  private const array EXCLUDED_DIRECTORIES = [
    '.git',
    'node_modules',
    'vendor',
  ];

  /**
   * Constructs a module source-file collector.
   */
  public function __construct(
    private readonly string $appRoot,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function collect(ModuleComponent $module): array {
    if ($module->sourcePath === NULL || $module->sourcePath === '') {
      return [];
    }

    $app_root = realpath($this->appRoot);
    $module_root = realpath(
      $this->appRoot . DIRECTORY_SEPARATOR . $module->sourcePath,
    );
    if (
      $app_root === FALSE
      || $module_root === FALSE
      || $module_root === $app_root
      || !$this->isInside($module_root, $app_root)
      || !is_dir($module_root)
    ) {
      return [];
    }

    $module_source_path = str_replace(
      DIRECTORY_SEPARATOR,
      '/',
      substr($module_root, strlen($app_root) + 1),
    );
    $records = [];
    try {
      $directory = new \RecursiveDirectoryIterator(
        $module_root,
        \FilesystemIterator::SKIP_DOTS,
      );
      $filter = new \RecursiveCallbackFilterIterator(
        $directory,
        static function (\SplFileInfo $file): bool {
          return !$file->isDir() || !in_array(
            $file->getFilename(),
            self::EXCLUDED_DIRECTORIES,
            TRUE,
          );
        },
      );
      $files = new \RecursiveIteratorIterator($filter);

      foreach ($files as $file) {
        if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->isLink()) {
          continue;
        }

        $real_path = $file->getRealPath();
        if ($real_path === FALSE || !$this->isInside($real_path, $module_root)) {
          continue;
        }

        $relative_path = str_replace(
          DIRECTORY_SEPARATOR,
          '/',
          substr($real_path, strlen($module_root) + 1),
        );
        $file_type = $this->fileType($relative_path);
        $records[] = new ModuleSourceFileComponent(
          moduleId: $module->id,
          relativePath: $relative_path,
          fileType: $file_type,
          category: $this->category($relative_path, $file_type),
          size: $file->getSize(),
          sourcePath: $module_source_path . '/' . $relative_path,
        );
      }
    }
    catch (\UnexpectedValueException) {
      // Return the metadata collected from readable directories.
    }

    usort(
      $records,
      static fn(ModuleSourceFileComponent $first, ModuleSourceFileComponent $second): int => $first->relativePath <=> $second->relativePath,
    );

    return $records;
  }

  /**
   * Determines whether a canonical path is inside a canonical directory.
   */
  private function isInside(string $path, string $directory): bool {
    return $path === $directory
      || str_starts_with($path, $directory . DIRECTORY_SEPARATOR);
  }

  /**
   * Classifies a file by its extension or Drupal procedural suffix.
   */
  private function fileType(string $relative_path): string {
    $extension = strtolower(pathinfo($relative_path, PATHINFO_EXTENSION));

    return match ($extension) {
      'php', 'module', 'install', 'theme', 'profile', 'inc', 'engine' => 'PHP',
      'yml', 'yaml' => 'YAML',
      'twig' => 'Twig',
      'js', 'mjs' => 'JavaScript',
      'css', 'scss' => 'CSS',
      'md', 'markdown' => 'Markdown',
      'json' => 'JSON',
      'xml' => 'XML',
      default => 'Other',
    };
  }

  /**
   * Assigns an architectural category without reading file contents.
   */
  private function category(
    string $relative_path,
    string $file_type,
  ): string {
    if (str_starts_with($relative_path, 'tests/')) {
      return 'Test';
    }
    if ($file_type === 'Markdown') {
      return 'Documentation';
    }
    if ($file_type === 'Twig') {
      return 'Template';
    }
    if (in_array($file_type, ['JavaScript', 'CSS'], TRUE)) {
      return 'Asset';
    }
    if (in_array($file_type, ['YAML', 'JSON', 'XML'], TRUE)) {
      return 'Configuration';
    }

    $extension = strtolower(pathinfo($relative_path, PATHINFO_EXTENSION));
    if (in_array($extension, ['module', 'install', 'theme', 'profile'], TRUE)) {
      return 'Procedural';
    }
    if ($file_type === 'PHP') {
      return 'Source code';
    }

    return 'Other';
  }

}
