<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Agent;

use Drupal\drupal_developer_assistant\Agent\GroundedAnswerRepairResponseParser;
use Drupal\drupal_developer_assistant\Retrieval\SourceSnippet;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests deterministic rendering of structured answer repairs.
 */
#[Group('drupal_developer_assistant')]
final class GroundedAnswerRepairResponseParserTest extends UnitTestCase {

  /**
   * Tests rendering of every supported evidence section.
   */
  public function testRendersStructuredRepair(): void {
    $response = json_encode([
      'confirmed' => [
        [
          'claim' => "Cron runs from\n the terminate handler -&gt; safely.",
          'evidence_index' => 0,
        ],
      ],
      'inferred' => ['The event occurs after the response.'],
      'unavailable' => ['Exact string literal values are redacted.'],
    ], JSON_THROW_ON_ERROR);

    $answer = (new GroundedAnswerRepairResponseParser())->parse(
      $response,
      'core/modules/automated_cron',
      [$this->snippet()],
    );

    $this->assertStringContainsString('### Confirmed by source', $answer);
    $this->assertStringContainsString(
      'Cron runs from the terminate handler -> safely. Evidence: `core/modules/automated_cron/src/EventSubscriber/AutomatedCron.php:31-43 — Drupal\automated_cron\EventSubscriber\AutomatedCron::onTerminate`',
      $answer,
    );
    $this->assertStringContainsString('### Inferred', $answer);
    $this->assertStringContainsString(
      '### Unavailable because redacted',
      $answer,
    );
  }

  /**
   * Tests rejection of malformed repair JSON.
   */
  public function testRejectsInvalidRepair(): void {
    $this->expectException(\UnexpectedValueException::class);
    (new GroundedAnswerRepairResponseParser())->parse(
      '{"confirmed":[]}',
      'core/modules/automated_cron',
      [$this->snippet()],
    );
  }

  /**
   * Creates retrieved evidence used to canonicalize repair citations.
   */
  private function snippet(): SourceSnippet {
    return new SourceSnippet(
      path: 'src/EventSubscriber/AutomatedCron.php',
      symbol: 'Drupal\automated_cron\EventSubscriber\AutomatedCron::onTerminate',
      kind: 'method',
      startLine: 31,
      endLine: 43,
      score: 10,
      matchedTerms: ['cron'],
      selectionReason: 'lexical_match',
      content: 'public function onTerminate(): void {}',
      truncated: FALSE,
      redactions: 1,
    );
  }

}
