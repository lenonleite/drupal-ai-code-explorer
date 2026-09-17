<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Agent;

/**
 * Validates a natural-language agent answer against retrieved source evidence.
 */
interface GroundedAnswerValidatorInterface {

  /**
   * Returns validation errors for an agent answer.
   *
   * @param string $answer
   *   The untrusted answer drafted by the agent.
   * @param string $question
   *   The original developer question.
   * @param string $module_path
   *   Drupal-root-relative module path.
   * @param list<\Drupal\drupal_developer_assistant\Retrieval\SourceSnippet> $snippets
   *   Bounded source evidence retrieved for the question.
   *
   * @return list<string>
   *   Empty when the answer passes validation.
   */
  public function validate(
    string $answer,
    string $question,
    string $module_path,
    array $snippets,
  ): array;

}
