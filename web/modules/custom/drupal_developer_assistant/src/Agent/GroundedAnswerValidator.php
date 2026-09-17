<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Agent;

use Drupal\drupal_developer_assistant\Retrieval\SourceSnippet;

/**
 * Applies deterministic grounding checks to a drafted agent answer.
 */
final class GroundedAnswerValidator implements GroundedAnswerValidatorInterface {

  private const int MAX_ANSWER_BYTES = 20_000;

  /**
   * Matches the required path, line range, and symbol citation.
   */
  private const string CITATION_PATTERN = '/Evidence:\s*`?([^`:\r\n]+):(\d+)(?:-(\d+))?\s+—\s+([^`\r\n]+)`?/u';

  /**
   * {@inheritdoc}
   */
  public function validate(
    string $answer,
    string $question,
    string $module_path,
    array $snippets,
  ): array {
    $errors = [];
    $answer = trim($answer);
    if ($answer === '') {
      return ['The answer is empty.'];
    }
    if (strlen($answer) > self::MAX_ANSWER_BYTES) {
      $errors[] = 'The answer exceeds the 20,000-byte safety limit.';
    }

    $confirmed = $this->section($answer, 'Confirmed by source');
    if ($confirmed === NULL) {
      $errors[] = 'The answer must contain a "### Confirmed by source" section.';
    }
    else {
      $claim_count = 0;
      foreach (preg_split('/\R/u', $confirmed) ?: [] as $line) {
        if (!str_starts_with(trim($line), '- ')) {
          continue;
        }
        $claim_count++;
        if (!preg_match(self::CITATION_PATTERN, $line)) {
          $errors[] = 'Every confirmed-source bullet must end with one path, line range, and symbol citation.';
        }
      }
      if ($claim_count === 0) {
        $errors[] = 'The confirmed-source section must contain at least one evidence bullet.';
      }
      if (str_contains($confirmed, '[string literal omitted]')) {
        $errors[] = 'A redaction marker must not be presented as confirmed source.';
      }
    }

    if ($this->redactionCount($snippets) > 0) {
      $redacted = $this->section($answer, 'Unavailable because redacted');
      if ($redacted === NULL || trim($redacted) === '') {
        $errors[] = 'The answer must describe unavailable values under "### Unavailable because redacted".';
      }
    }

    if (
      str_contains($answer, '```')
      && !$this->explicitlyRequestsCode($question)
    ) {
      $errors[] = 'The answer includes a code block that the question did not request.';
    }
    if (preg_match('/&(?:[a-z][a-z0-9]+|#[0-9]+|#x[a-f0-9]+);/iu', $answer)) {
      $errors[] = 'The answer must contain plain text, not HTML entities.';
    }

    preg_match_all(
      self::CITATION_PATTERN,
      $answer,
      $citations,
      PREG_SET_ORDER,
    );
    if ($citations === []) {
      $errors[] = 'The answer must contain at least one source citation.';
    }
    else {
      $catalog = $this->catalog($module_path, $snippets);
      foreach ($citations as $citation) {
        $path = trim($citation[1]);
        $start_line = (int) $citation[2];
        $end_line = isset($citation[3]) && $citation[3] !== ''
          ? (int) $citation[3]
          : $start_line;
        $symbol = trim($citation[4], " `\t\n\r\0\x0B.");
        if (!isset($catalog[$path][$symbol])) {
          $errors[] = sprintf(
            'Citation "%s — %s" was not present in retrieved source evidence.',
            $path,
            $symbol,
          );
          continue;
        }
        $range = $catalog[$path][$symbol];
        if ($start_line < $range['start'] || $end_line > $range['end']) {
          $errors[] = sprintf(
            'Citation lines %d-%d are outside retrieved lines %d-%d for "%s".',
            $start_line,
            $end_line,
            $range['start'],
            $range['end'],
            $symbol,
          );
        }
      }
    }

    return array_values(array_unique($errors));
  }

  /**
   * Extracts one level-three Markdown section case-insensitively.
   */
  private function section(string $answer, string $heading): ?string {
    $pattern = sprintf(
      '/^###\s+%s\s*$\R(?<body>.*?)(?=^###\s+|\z)/imsu',
      preg_quote($heading, '/'),
    );
    return preg_match($pattern, $answer, $match) ? $match['body'] : NULL;
  }

  /**
   * Counts redactions reported by retrieved snippets.
   *
   * @param list<\Drupal\drupal_developer_assistant\Retrieval\SourceSnippet> $snippets
   *   Retrieved snippets.
   */
  private function redactionCount(array $snippets): int {
    return array_sum(array_map(
      static fn(SourceSnippet $snippet): int => $snippet->redactions,
      $snippets,
    ));
  }

  /**
   * Determines whether the developer explicitly requested displayed code.
   */
  private function explicitlyRequestsCode(string $question): bool {
    return (bool) preg_match(
      '/(?:show|include|quote|display|provide|give)(?:\s+\S+){0,5}\s+(?:code|source|snippet)|code\s+example/iu',
      $question,
    );
  }

  /**
   * Builds accepted citation aliases from retrieved source evidence.
   *
   * @param string $module_path
   *   Drupal-root-relative module path.
   * @param list<\Drupal\drupal_developer_assistant\Retrieval\SourceSnippet> $snippets
   *   Retrieved snippets.
   *
   * @return array<string, array<string, array{start: int, end: int}>>
   *   Paths containing symbols and their retrieved line ranges.
   */
  private function catalog(string $module_path, array $snippets): array {
    $catalog = [];
    foreach ($snippets as $snippet) {
      $range = [
        'start' => $snippet->startLine,
        'end' => $snippet->endLine,
      ];
      $catalog[$snippet->path][$snippet->symbol] = $range;
      $full_path = rtrim($module_path, '/') . '/' . ltrim($snippet->path, '/');
      $catalog[$full_path][$snippet->symbol] = $range;
    }
    return $catalog;
  }

}
