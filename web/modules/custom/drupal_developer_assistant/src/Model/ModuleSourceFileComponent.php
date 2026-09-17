<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Model;

/**
 * Represents one source file discovered inside a Drupal module.
 */
final readonly class ModuleSourceFileComponent extends Component {

  /**
   * Constructs a discovered module source file.
   *
   * @param string $moduleId
   *   The owning module machine name.
   * @param string $relativePath
   *   The file path relative to the module directory.
   * @param string $fileType
   *   The normalized file format, such as PHP, YAML, or Twig.
   * @param string $category
   *   The architectural category, such as Source code or Configuration.
   * @param int $size
   *   The file size in bytes.
   * @param string $sourcePath
   *   The file path relative to the Drupal application root.
   */
  public function __construct(
    public string $moduleId,
    public string $relativePath,
    public string $fileType,
    public string $category,
    public int $size,
    string $sourcePath,
  ) {
    parent::__construct(
      id: $moduleId . ':' . $relativePath,
      type: 'source_file',
      label: $relativePath,
      sourcePath: $sourcePath,
    );
  }

}
