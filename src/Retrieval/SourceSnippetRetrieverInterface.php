<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Retrieval;

use Drupal\drupal_developer_assistant\Model\ModuleArchitecture;

/**
 * Retrieves bounded source evidence relevant to a developer question.
 */
interface SourceSnippetRetrieverInterface {

  /**
   * Retrieves local source snippets without executing inspected code.
   *
   * @return list<\Drupal\drupal_developer_assistant\Retrieval\SourceSnippet>
   *   Ranked, bounded, and redacted source evidence.
   */
  public function retrieve(
    ModuleArchitecture $architecture,
    string $question,
  ): array;

}
