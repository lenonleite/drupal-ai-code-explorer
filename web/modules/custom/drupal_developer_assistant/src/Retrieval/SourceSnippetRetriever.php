<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Retrieval;

use Drupal\drupal_developer_assistant\Model\ModuleArchitecture;
use Drupal\drupal_developer_assistant\Model\ModuleComponent;
use Drupal\drupal_developer_assistant\Model\ModuleSourceFileComponent;
use Drupal\drupal_developer_assistant\Model\PhpFileAnalysis;

/**
 * Retrieves question-relevant PHP declarations under strict local boundaries.
 */
final class SourceSnippetRetriever implements SourceSnippetRetrieverInterface {

  /**
   * Constructs a PHP source snippet retriever.
   */
  public function __construct(
    private readonly string $appRoot,
    private readonly SourceRetrievalLimits $limits,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function retrieve(
    ModuleArchitecture $architecture,
    string $question,
  ): array {
    $query_terms = array_slice(
      $this->terms($question),
      0,
      $this->limits->maxQuestionTerms,
    );
    $include_tests = array_intersect(
      $query_terms,
      ['test', 'tests', 'testing'],
    ) !== [];
    $candidates = $this->candidates($architecture, $include_tests);
    $candidates = $this->rank($candidates, $query_terms);
    $matched_candidates = array_values(array_filter(
      $candidates,
      static fn(array $candidate): bool => $candidate['score'] > 0,
    ));
    $selection_reason = 'lexical_match';
    if ($matched_candidates === []) {
      $matched_candidates = array_values(array_filter(
        $candidates,
        static fn(array $candidate): bool => $candidate['kind'] === 'class',
      ));
      if ($matched_candidates === []) {
        $matched_candidates = $candidates;
      }
      $selection_reason = 'production_fallback';
    }

    $snippets = [];
    $selected_ranges = [];
    $total_bytes = 0;
    foreach ($matched_candidates as $candidate) {
      if (count($snippets) >= $this->limits->maxSnippets) {
        break;
      }
      if ($this->overlaps($candidate, $selected_ranges)) {
        continue;
      }

      $remaining_bytes = $this->limits->maxTotalBytes - $total_bytes;
      if ($remaining_bytes < 4) {
        break;
      }
      $snippet = $this->snippet(
        $architecture->module,
        $candidate,
        $selection_reason,
        $remaining_bytes,
      );
      if ($snippet === NULL) {
        continue;
      }

      $snippets[] = $snippet;
      $selected_ranges[] = [
        'path' => $candidate['file']->sourceFile->relativePath,
        'start' => $candidate['start'],
        'end' => $candidate['end'],
      ];
      $total_bytes += strlen($snippet->content);
    }

    return $snippets;
  }

  /**
   * Builds source declaration candidates from existing PHP analysis.
   *
   * @return list<array<string, mixed>>
   *   Candidate declarations with their searchable terms and source ranges.
   */
  private function candidates(
    ModuleArchitecture $architecture,
    bool $include_tests,
  ): array {
    $candidates = [];
    foreach ($architecture->phpFiles as $file) {
      if (
        $file->error !== NULL
        || (!$include_tests && $file->sourceFile->category === 'Test')
      ) {
        continue;
      }

      $path_terms = $this->terms($file->sourceFile->relativePath);
      foreach ($file->symbols as $symbol) {
        if ($symbol->startLine === NULL || $symbol->endLine === NULL) {
          continue;
        }
        $symbol_terms = $this->terms(
          $symbol->fullyQualifiedName . ' ' . $symbol->name,
        );
        $symbol_context = $this->terms(implode(' ', [
          ...$path_terms,
          ...$symbol->extends,
          ...$symbol->implements,
          ...$symbol->traits,
          ...array_map(
            static fn($property): string => ($property->type ?? '')
              . ' ' . $property->name,
            $symbol->properties,
          ),
        ]));
        $candidates[] = $this->candidate(
          $file,
          $symbol->fullyQualifiedName,
          $symbol->kind,
          $symbol->startLine,
          $symbol->endLine,
          $symbol_terms,
          [],
          $symbol_context,
        );

        foreach ($symbol->methods as $method) {
          if ($method->startLine === NULL || $method->endLine === NULL) {
            continue;
          }
          $method_context = $this->terms(implode(' ', [
            ...$path_terms,
            $method->returnType ?? '',
            ...array_map(
              static fn($parameter): string => ($parameter->type ?? '')
                . ' ' . $parameter->name,
              $method->parameters,
            ),
          ]));
          $candidates[] = $this->candidate(
            $file,
            $symbol->fullyQualifiedName . '::' . $method->name,
            'method',
            $method->startLine,
            $method->endLine,
            $this->terms($method->name),
            $symbol_terms,
            $method_context,
          );
        }
      }

      foreach ($file->functions as $function) {
        if ($function->startLine === NULL || $function->endLine === NULL) {
          continue;
        }
        $function_context = $this->terms(implode(' ', [
          ...$path_terms,
          $function->returnType ?? '',
          ...array_map(
            static fn($parameter): string => ($parameter->type ?? '')
              . ' ' . $parameter->name,
            $function->parameters,
          ),
        ]));
        $candidates[] = $this->candidate(
          $file,
          $function->fullyQualifiedName,
          'function',
          $function->startLine,
          $function->endLine,
          $this->terms($function->fullyQualifiedName),
          [],
          $function_context,
        );
      }
    }

    return $candidates;
  }

  /**
   * Creates one unscored source candidate.
   *
   * @param \Drupal\drupal_developer_assistant\Model\PhpFileAnalysis $file
   *   Analyzed PHP file containing the declaration.
   * @param string $symbol
   *   Fully qualified declaration name.
   * @param string $kind
   *   Declaration kind.
   * @param int $start
   *   First declaration line.
   * @param int $end
   *   Last declaration line.
   * @param list<string> $name_terms
   *   Terms from the declaration's own name.
   * @param list<string> $owner_terms
   *   Terms from the containing class-like symbol.
   * @param list<string> $context_terms
   *   Terms from paths, types, and related declaration metadata.
   *
   * @return array<string, mixed>
   *   A source candidate ready for ranking.
   */
  private function candidate(
    PhpFileAnalysis $file,
    string $symbol,
    string $kind,
    int $start,
    int $end,
    array $name_terms,
    array $owner_terms,
    array $context_terms,
  ): array {
    return [
      'file' => $file,
      'symbol' => $symbol,
      'kind' => $kind,
      'start' => $start,
      'end' => $end,
      'name_terms' => $name_terms,
      'owner_terms' => $owner_terms,
      'context_terms' => $context_terms,
      'score' => 0,
      'matched_terms' => [],
    ];
  }

  /**
   * Scores and deterministically orders source candidates.
   *
   * @param list<array<string, mixed>> $candidates
   *   Unscored source candidates.
   * @param list<string> $query_terms
   *   Normalized developer-question terms.
   *
   * @return list<array<string, mixed>>
   *   Candidates ordered by relevance and stable source position.
   */
  private function rank(array $candidates, array $query_terms): array {
    foreach ($candidates as &$candidate) {
      foreach ($query_terms as $term) {
        $term_score = match (TRUE) {
          in_array($term, $candidate['name_terms'], TRUE) => 20,
          in_array($term, $candidate['owner_terms'], TRUE) => 10,
          in_array($term, $candidate['context_terms'], TRUE) => 4,
          default => 0,
        };
        if ($term_score > 0) {
          $candidate['score'] += $term_score;
          $candidate['matched_terms'][] = $term;
        }
      }
      $candidate['matched_terms'] = array_values(array_unique(
        $candidate['matched_terms'],
      ));
    }
    unset($candidate);

    $kind_priority = ['class' => 0, 'method' => 1, 'function' => 2];
    usort(
      $candidates,
      static function (array $first, array $second) use ($kind_priority): int {
        return $second['score'] <=> $first['score']
          ?: ($kind_priority[$first['kind']] ?? 3)
            <=> ($kind_priority[$second['kind']] ?? 3)
          ?: $first['file']->sourceFile->relativePath
            <=> $second['file']->sourceFile->relativePath
          ?: $first['start'] <=> $second['start'];
      },
    );

    return $candidates;
  }

  /**
   * Extracts, redacts, and bounds one candidate source range.
   *
   * @param \Drupal\drupal_developer_assistant\Model\ModuleComponent $module
   *   Module that owns the candidate source file.
   * @param array<string, mixed> $candidate
   *   Ranked source candidate metadata.
   * @param string $selection_reason
   *   Reason the candidate was selected.
   * @param int $remaining_bytes
   *   Bytes still available under the total retrieval limit.
   */
  private function snippet(
    ModuleComponent $module,
    array $candidate,
    string $selection_reason,
    int $remaining_bytes,
  ): ?SourceSnippet {
    $file = $candidate['file']->sourceFile;
    $code = $this->sourceCode($module, $file);
    if ($code === NULL) {
      return NULL;
    }

    $lines = preg_split('/\R/u', $code);
    if ($lines === FALSE || $lines === []) {
      return NULL;
    }
    $start = max(1, $candidate['start']);
    $requested_end = min(count($lines), $candidate['end']);
    $end = min(
      $requested_end,
      $start + $this->limits->maxSnippetLines - 1,
    );
    if ($end < $start) {
      return NULL;
    }

    $content = implode("\n", array_slice(
      $lines,
      $start - 1,
      $end - $start + 1,
    ));
    [$content, $redactions] = $this->redact($content);
    $byte_limit = min($this->limits->maxSnippetBytes, $remaining_bytes);
    $truncated = $end < $requested_end;
    if (strlen($content) > $byte_limit) {
      $content = mb_strcut($content, 0, $byte_limit - 3, 'UTF-8') . '...';
      $truncated = TRUE;
    }

    return new SourceSnippet(
      path: $file->relativePath,
      symbol: $candidate['symbol'],
      kind: $candidate['kind'],
      startLine: $start,
      endLine: $end,
      score: $candidate['score'],
      matchedTerms: $candidate['matched_terms'],
      selectionReason: $selection_reason,
      content: $content,
      truncated: $truncated,
      redactions: $redactions,
    );
  }

  /**
   * Reads a validated PHP source file inside the selected module.
   */
  private function sourceCode(
    ModuleComponent $module,
    ModuleSourceFileComponent $source_file,
  ): ?string {
    if (
      $source_file->moduleId !== $module->id
      || $source_file->fileType !== 'PHP'
      || $module->sourcePath === NULL
    ) {
      return NULL;
    }

    $app_root = realpath($this->appRoot);
    $module_root = realpath(
      $this->appRoot . DIRECTORY_SEPARATOR . $module->sourcePath,
    );
    $source_path = $this->appRoot
      . DIRECTORY_SEPARATOR
      . $source_file->sourcePath;
    $file_path = realpath($source_path);
    $expected_path = $module_root === FALSE
      ? FALSE
      : realpath($module_root . DIRECTORY_SEPARATOR . $source_file->relativePath);
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
    $code = file_get_contents($file_path);
    if ($code === FALSE || !mb_check_encoding($code, 'UTF-8')) {
      return NULL;
    }

    return $code;
  }

  /**
   * Removes comments, inline HTML, and PHP string literal values.
   *
   * @return array{0: string, 1: int}
   *   Redacted code and the number of replaced token fragments.
   */
  private function redact(string $content): array {
    $redacted = '';
    $redactions = 0;
    foreach (\PhpToken::tokenize("<?php\n" . $content) as $token) {
      if ($token->id === T_OPEN_TAG) {
        continue;
      }
      if (in_array($token->id, [T_COMMENT, T_DOC_COMMENT], TRUE)) {
        $redacted .= '/* comment omitted */'
          . str_repeat("\n", substr_count($token->text, "\n"));
        $redactions++;
        continue;
      }
      if ($token->id === T_CONSTANT_ENCAPSED_STRING) {
        $redacted .= "'[string literal omitted]'";
        $redactions++;
        continue;
      }
      if ($token->id === T_ENCAPSED_AND_WHITESPACE) {
        $redacted .= '[string literal omitted]';
        $redactions++;
        continue;
      }
      if ($token->id === T_INLINE_HTML) {
        $redacted .= '[inline HTML omitted]';
        $redactions++;
        continue;
      }

      $redacted .= $token->text;
    }

    return [ltrim($redacted, "\n"), $redactions];
  }

  /**
   * Determines whether a candidate overlaps an already selected source range.
   *
   * @param array<string, mixed> $candidate
   *   Candidate source range.
   * @param list<array{path: string, start: int, end: int}> $selected_ranges
   *   Previously selected ranges.
   */
  private function overlaps(array $candidate, array $selected_ranges): bool {
    foreach ($selected_ranges as $range) {
      if (
        $range['path'] === $candidate['file']->sourceFile->relativePath
        && $candidate['start'] <= $range['end']
        && $candidate['end'] >= $range['start']
      ) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Splits prose, paths, namespaces, and camel-case names into search terms.
   *
   * @return list<string>
   *   Unique lowercase terms with at least three characters.
   */
  private function terms(string $value): array {
    $value = preg_replace(
      '/(?<=[\p{Ll}\d])(?=\p{Lu})/u',
      ' ',
      $value,
    ) ?? $value;
    $parts = preg_split(
      '/[^\p{L}\p{N}]+/u',
      mb_strtolower($value, 'UTF-8'),
    ) ?: [];

    return array_values(array_unique(array_filter(
      $parts,
      static fn(string $part): bool => mb_strlen($part, 'UTF-8') >= 3,
    )));
  }

  /**
   * Determines whether a canonical path is inside a canonical directory.
   */
  private function isInside(string $path, string $directory): bool {
    return $path === $directory
      || str_starts_with($path, $directory . DIRECTORY_SEPARATOR);
  }

}
