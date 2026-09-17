<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Agent;

/**
 * Repairs one invalid natural-language agent answer.
 */
interface GroundedAnswerRepairerInterface {

  /**
   * Makes one provider request to repair an invalid answer.
   *
   * @param string $question
   *   Original developer question.
   * @param string $candidate
   *   Untrusted answer that failed validation.
   * @param list<string> $validation_errors
   *   Deterministic validation failures.
   * @param string $module_path
   *   Drupal-root-relative module path.
   * @param list<\Drupal\drupal_developer_assistant\Retrieval\SourceSnippet> $snippets
   *   Retrieved source evidence.
   */
  public function repair(
    string $question,
    string $candidate,
    array $validation_errors,
    string $module_path,
    array $snippets,
  ): string;

}
