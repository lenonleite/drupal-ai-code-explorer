<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Retrieval;

/**
 * Defines safety and size limits for local PHP source retrieval.
 */
final readonly class SourceRetrievalLimits {

  /**
   * Constructs a source retrieval limit set.
   */
  public function __construct(
    public int $maxSnippets = 3,
    public int $maxSnippetLines = 120,
    public int $maxSnippetBytes = 8_000,
    public int $maxTotalBytes = 16_000,
    public int $maxFileBytes = 1_048_576,
    public int $maxQuestionTerms = 20,
  ) {
    foreach (get_object_vars($this) as $name => $value) {
      if ($value < 1) {
        throw new \InvalidArgumentException(sprintf(
          'Source retrieval limit "%s" must be positive.',
          $name,
        ));
      }
    }
    if ($this->maxSnippetBytes < 4 || $this->maxTotalBytes < 4) {
      throw new \InvalidArgumentException(
        'Source retrieval byte limits must allow the truncation marker.',
      );
    }
  }

}
