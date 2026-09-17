<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Evidence;

/**
 * Represents a safely resolved, bounded source-evidence preview.
 */
final readonly class ModuleSourceEvidence {

  /**
   * Constructs a source-evidence preview.
   *
   * @param string $sourcePath
   *   Source path spelling used by the AI citation.
   * @param string $symbol
   *   Fully qualified symbol, or an empty string for file-level evidence.
   * @param string $kind
   *   Evidence kind, such as file, class, method, or function.
   * @param string $fileType
   *   Normalized source-file type.
   * @param string $category
   *   Architectural file category.
   * @param int|null $startLine
   *   First displayed source line, when a text preview is available.
   * @param int|null $endLine
   *   Last displayed source line, when a text preview is available.
   * @param string|null $content
   *   Escaped-at-render bounded source text, when previewable.
   * @param bool $truncated
   *   Whether source was omitted because of line or byte limits.
   */
  public function __construct(
    public string $sourcePath,
    public string $symbol,
    public string $kind,
    public string $fileType,
    public string $category,
    public ?int $startLine,
    public ?int $endLine,
    public ?string $content,
    public bool $truncated,
  ) {}

}
