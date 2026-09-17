<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Retrieval;

/**
 * Represents one bounded and redacted source-code excerpt.
 */
final readonly class SourceSnippet implements \JsonSerializable {

  /**
   * Constructs a retrieved source snippet.
   *
   * @param string $path
   *   Module-relative path containing the declaration.
   * @param string $symbol
   *   Fully qualified declaration name.
   * @param string $kind
   *   Declaration kind, such as class, method, or function.
   * @param int $startLine
   *   First source line included in the excerpt.
   * @param int $endLine
   *   Last source line included in the excerpt.
   * @param int $score
   *   Lexical relevance score for the developer question.
   * @param list<string> $matchedTerms
   *   Normalized developer-question terms matched by this excerpt.
   * @param string $selectionReason
   *   Reason this declaration was selected.
   * @param string $content
   *   Bounded source code with sensitive text removed.
   * @param bool $truncated
   *   Whether the declaration was shortened by a retrieval limit.
   * @param int $redactions
   *   Number of comments or literal fragments removed.
   */
  public function __construct(
    public string $path,
    public string $symbol,
    public string $kind,
    public int $startLine,
    public int $endLine,
    public int $score,
    public array $matchedTerms,
    public string $selectionReason,
    public string $content,
    public bool $truncated,
    public int $redactions,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function jsonSerialize(): array {
    return [
      'path' => $this->path,
      'symbol' => $this->symbol,
      'kind' => $this->kind,
      'start_line' => $this->startLine,
      'end_line' => $this->endLine,
      'score' => $this->score,
      'matched_terms' => $this->matchedTerms,
      'selection_reason' => $this->selectionReason,
      'content' => $this->content,
      'truncated' => $this->truncated,
      'redactions' => $this->redactions,
    ];
  }

}
