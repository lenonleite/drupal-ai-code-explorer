<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Agent;

/**
 * Parses structured repair data and renders deterministic Markdown.
 */
final class GroundedAnswerRepairResponseParser {

  /**
   * Parses and renders one structured repair response.
   */
  public function parse(
    string $response,
    string $module_path,
    array $snippets,
  ): string {
    try {
      $data = json_decode($response, TRUE, flags: JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $exception) {
      throw new \UnexpectedValueException(
        'The grounded-answer repair response was not valid JSON.',
        previous: $exception,
      );
    }
    if (!is_array($data)) {
      throw new \UnexpectedValueException(
        'The grounded-answer repair response must be a JSON object.',
      );
    }
    foreach (['confirmed', 'inferred', 'unavailable'] as $key) {
      if (!isset($data[$key]) || !is_array($data[$key])) {
        throw new \UnexpectedValueException(sprintf(
          'The grounded-answer repair response requires an array named "%s".',
          $key,
        ));
      }
    }
    if ($data['confirmed'] === []) {
      throw new \UnexpectedValueException(
        'The grounded-answer repair response requires confirmed evidence.',
      );
    }

    $lines = ['### Confirmed by source'];
    foreach ($data['confirmed'] as $item) {
      if (!is_array($item)) {
        throw new \UnexpectedValueException(
          'Each confirmed item must be an object.',
        );
      }
      foreach (['claim', 'evidence_index'] as $key) {
        if (!array_key_exists($key, $item)) {
          throw new \UnexpectedValueException(sprintf(
            'A confirmed item is missing "%s".',
            $key,
          ));
        }
      }
      $claim = $this->singleLine($item['claim']);
      $evidence_index = filter_var(
        $item['evidence_index'],
        FILTER_VALIDATE_INT,
      );
      if ($claim === '' || $evidence_index === FALSE) {
        throw new \UnexpectedValueException(
          'A confirmed item contains invalid values.',
        );
      }
      if (!isset($snippets[$evidence_index])) {
        throw new \UnexpectedValueException(
          'A confirmed item references an unavailable evidence index.',
        );
      }
      $snippet = $snippets[$evidence_index];
      $path = rtrim($module_path, '/') . '/'
        . ltrim($snippet->path, '/');
      $lines[] = sprintf(
        '- %s Evidence: `%s:%d-%d — %s`',
        $claim,
        $path,
        $snippet->startLine,
        $snippet->endLine,
        $snippet->symbol,
      );
    }
    $this->appendTextSection($lines, 'Inferred', $data['inferred']);
    $this->appendTextSection(
      $lines,
      'Unavailable because redacted',
      $data['unavailable'],
    );
    return implode("\n", $lines);
  }

  /**
   * Adds an optional Markdown section containing plain one-line bullets.
   *
   * @param list<string> $lines
   *   Rendered output lines.
   * @param string $heading
   *   Level-three heading text.
   * @param mixed $items
   *   Candidate list of narrative items.
   */
  private function appendTextSection(
    array &$lines,
    string $heading,
    mixed $items,
  ): void {
    if ($items === []) {
      return;
    }
    $rendered = [];
    foreach ($items as $item) {
      $item = $this->singleLine($item);
      if ($item === '') {
        throw new \UnexpectedValueException(sprintf(
          'The "%s" section contains an invalid item.',
          $heading,
        ));
      }
      $rendered[] = '- ' . $item;
    }
    $lines[] = '';
    $lines[] = '### ' . $heading;
    array_push($lines, ...$rendered);
  }

  /**
   * Normalizes an untrusted scalar to one Markdown line.
   */
  private function singleLine(mixed $value): string {
    if (!is_string($value)) {
      return '';
    }
    $value = html_entity_decode(
      $value,
      ENT_QUOTES | ENT_HTML5,
      'UTF-8',
    );
    $value = strip_tags($value);
    return trim((string) preg_replace('/\s+/u', ' ', $value));
  }

}
