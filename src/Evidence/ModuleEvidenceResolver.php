<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Evidence;

use Drupal\drupal_developer_assistant\Model\ModuleArchitecture;
use Drupal\drupal_developer_assistant\Model\ModuleSourceFileComponent;
use Drupal\drupal_developer_assistant\Retrieval\SourceRetrievalLimits;

/**
 * Resolves opaque citations to bounded files inside a discovered module.
 */
final readonly class ModuleEvidenceResolver implements ModuleEvidenceResolverInterface {

  /**
   * Text file types that may be displayed as source.
   */
  private const array PREVIEWABLE_TYPES = [
    'PHP',
    'YAML',
    'Twig',
    'JavaScript',
    'CSS',
    'Markdown',
    'JSON',
    'XML',
  ];

  /**
   * Constructs a module evidence resolver.
   */
  public function __construct(
    private string $appRoot,
    private SourceRetrievalLimits $limits,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function referenceId(
    string $module_id,
    string $source_path,
    string $symbol,
  ): string {
    return hash('sha256', implode("\0", [
      'module-evidence-v1',
      $module_id,
      $source_path,
      $symbol,
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(
    ModuleArchitecture $architecture,
    string $evidence_id,
  ): ?ModuleSourceEvidence {
    if (!preg_match('/\A[a-f0-9]{64}\z/', $evidence_id)) {
      return NULL;
    }

    foreach ($architecture->sourceFiles as $source_file) {
      foreach ($this->aliases($source_file) as $source_path) {
        if ($this->matches(
          $architecture,
          $source_path,
          '',
          $evidence_id,
        )) {
          return $this->preview(
            $architecture,
            $source_file,
            $source_path,
            '',
            'file',
            NULL,
            NULL,
          );
        }
      }

      foreach ($architecture->phpFiles as $php_file) {
        if ($php_file->sourceFile->relativePath !== $source_file->relativePath) {
          continue;
        }
        foreach ($php_file->symbols as $symbol) {
          foreach ($this->aliases($source_file) as $source_path) {
            if ($this->matches(
              $architecture,
              $source_path,
              $symbol->fullyQualifiedName,
              $evidence_id,
            )) {
              return $this->preview(
                $architecture,
                $source_file,
                $source_path,
                $symbol->fullyQualifiedName,
                $symbol->kind,
                $symbol->startLine,
                $symbol->endLine,
              );
            }
          }
          foreach ($symbol->methods as $method) {
            $method_name = $symbol->fullyQualifiedName . '::' . $method->name;
            foreach ($this->aliases($source_file) as $source_path) {
              if ($this->matches(
                $architecture,
                $source_path,
                $method_name,
                $evidence_id,
              )) {
                return $this->preview(
                  $architecture,
                  $source_file,
                  $source_path,
                  $method_name,
                  'method',
                  $method->startLine,
                  $method->endLine,
                );
              }
            }
          }
        }
        foreach ($php_file->functions as $function) {
          foreach ($this->aliases($source_file) as $source_path) {
            if ($this->matches(
              $architecture,
              $source_path,
              $function->fullyQualifiedName,
              $evidence_id,
            )) {
              return $this->preview(
                $architecture,
                $source_file,
                $source_path,
                $function->fullyQualifiedName,
                'function',
                $function->startLine,
                $function->endLine,
              );
            }
          }
        }
      }
    }

    return NULL;
  }

  /**
   * Returns accepted relative and Drupal-root-relative path spellings.
   *
   * @return list<string>
   *   Exact aliases exposed in AI context.
   */
  private function aliases(ModuleSourceFileComponent $source_file): array {
    return array_values(array_unique([
      $source_file->relativePath,
      $source_file->sourcePath,
    ]));
  }

  /**
   * Tests whether one known reference produces the requested opaque ID.
   */
  private function matches(
    ModuleArchitecture $architecture,
    string $source_path,
    string $symbol,
    string $evidence_id,
  ): bool {
    return hash_equals(
      $this->referenceId(
        $architecture->module->id,
        $source_path,
        $symbol,
      ),
      $evidence_id,
    );
  }

  /**
   * Builds a source preview after revalidating its canonical filesystem path.
   */
  private function preview(
    ModuleArchitecture $architecture,
    ModuleSourceFileComponent $source_file,
    string $source_path,
    string $symbol,
    string $kind,
    ?int $declaration_start,
    ?int $declaration_end,
  ): ModuleSourceEvidence {
    $content = NULL;
    $start_line = NULL;
    $end_line = NULL;
    $truncated = FALSE;
    $code = $this->sourceCode($architecture, $source_file);
    if ($code !== NULL) {
      $lines = preg_split('/\R/u', $code);
      if ($lines !== FALSE && $lines !== []) {
        $total_lines = count($lines);
        $requested_start = $declaration_start === NULL
          ? 1
          : max(1, $declaration_start - 3);
        $requested_end = $declaration_end === NULL
          ? $total_lines
          : min($total_lines, $declaration_end + 3);
        $start_line = min($requested_start, $total_lines);
        $end_line = min(
          $requested_end,
          $start_line + $this->limits->maxSnippetLines - 1,
        );
        $truncated = $start_line > 1 || $end_line < $total_lines;
        $selected = array_slice(
          $lines,
          $start_line - 1,
          $end_line - $start_line + 1,
        );
        $numbered = [];
        foreach ($selected as $offset => $line) {
          $numbered[] = sprintf('%5d | %s', $start_line + $offset, $line);
        }
        $content = implode("\n", $numbered);
        if (strlen($content) > $this->limits->maxSnippetBytes) {
          $content = mb_strcut(
            $content,
            0,
            $this->limits->maxSnippetBytes - 3,
            'UTF-8',
          ) . '...';
          $end_line = $start_line + substr_count($content, "\n");
          $truncated = TRUE;
        }
      }
    }

    return new ModuleSourceEvidence(
      sourcePath: $source_path,
      symbol: $symbol,
      kind: $kind,
      fileType: $source_file->fileType,
      category: $source_file->category,
      startLine: $start_line,
      endLine: $end_line,
      content: $content,
      truncated: $truncated,
    );
  }

  /**
   * Reads a known text file only after canonical containment checks.
   */
  private function sourceCode(
    ModuleArchitecture $architecture,
    ModuleSourceFileComponent $source_file,
  ): ?string {
    if (
      !in_array($source_file->fileType, self::PREVIEWABLE_TYPES, TRUE)
      || $source_file->size > $this->limits->maxFileBytes
      || $architecture->module->sourcePath === NULL
    ) {
      return NULL;
    }

    $app_root = realpath($this->appRoot);
    $module_root = realpath(
      $this->appRoot . DIRECTORY_SEPARATOR . $architecture->module->sourcePath,
    );
    $source_path = $this->appRoot
      . DIRECTORY_SEPARATOR
      . $source_file->sourcePath;
    $file_path = realpath($source_path);
    $expected_path = $module_root === FALSE
      ? FALSE
      : realpath(
        $module_root . DIRECTORY_SEPARATOR . $source_file->relativePath,
      );
    if (
      $app_root === FALSE
      || $module_root === FALSE
      || $module_root === $app_root
      || !$this->isInside($module_root, $app_root)
      || $file_path === FALSE
      || $expected_path === FALSE
      || $file_path !== $expected_path
      || !$this->isInside($file_path, $module_root)
      || !is_file($file_path)
      || is_link($source_path)
    ) {
      return NULL;
    }

    $size = filesize($file_path);
    if ($size === FALSE || $size > $this->limits->maxFileBytes) {
      return NULL;
    }
    $content = file_get_contents($file_path);
    if ($content === FALSE || !mb_check_encoding($content, 'UTF-8')) {
      return NULL;
    }

    return $content;
  }

  /**
   * Determines whether a canonical path is inside a canonical directory.
   */
  private function isInside(string $path, string $directory): bool {
    return $path === $directory
      || str_starts_with($path, $directory . DIRECTORY_SEPARATOR);
  }

}
