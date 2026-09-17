<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Agent;

use Drupal\drupal_developer_assistant\Agent\GroundedAnswerValidator;
use Drupal\drupal_developer_assistant\Retrieval\SourceSnippet;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests deterministic validation of grounded agent answers.
 */
#[Group('drupal_developer_assistant')]
final class GroundedAnswerValidatorTest extends UnitTestCase {

  /**
   * Validator under test.
   */
  private GroundedAnswerValidator $validator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->validator = new GroundedAnswerValidator();
  }

  /**
   * Tests a cited answer that acknowledges redacted values.
   */
  public function testValidGroundedAnswer(): void {
    $answer = <<<'MARKDOWN'
### Confirmed by source
- Cron runs from the terminate handler. Evidence: `core/modules/automated_cron/src/EventSubscriber/AutomatedCron.php:31-43 — Drupal\automated_cron\EventSubscriber\AutomatedCron::onTerminate`

### Unavailable because redacted
- The exact configuration and state keys cannot be confirmed.
MARKDOWN;

    $this->assertSame([], $this->validator->validate(
      $answer,
      'How does cron run after an HTTP response?',
      'core/modules/automated_cron',
      [$this->snippet()],
    ));
  }

  /**
   * Tests missing evidence structure and an invented citation.
   */
  public function testRejectsUngroundedAnswer(): void {
    $answer = <<<'MARKDOWN'
### Confirmed by source
- Cron runs automatically. Evidence: `core/modules/automated_cron/src/Missing.php:1-5 — Missing::run`
MARKDOWN;

    $errors = $this->validator->validate(
      $answer,
      'How does cron run?',
      'core/modules/automated_cron',
      [$this->snippet()],
    );

    $this->assertNotEmpty($errors);
    $this->assertStringContainsString(
      'Unavailable because redacted',
      implode(' ', $errors),
    );
    $this->assertStringContainsString(
      'was not present in retrieved source evidence',
      implode(' ', $errors),
    );
  }

  /**
   * Tests rejection of unrequested code and encoded HTML.
   */
  public function testRejectsPresentationViolations(): void {
    $answer = <<<'MARKDOWN'
### Confirmed by source
- Cron calls a service. Evidence: `src/EventSubscriber/AutomatedCron.php:31-43 — Drupal\automated_cron\EventSubscriber\AutomatedCron::onTerminate`

```php
$service-&gt;run();
```

### Unavailable because redacted
- Exact values are unavailable.
MARKDOWN;
    $errors = $this->validator->validate(
      $answer,
      'Explain the cron behavior.',
      'core/modules/automated_cron',
      [$this->snippet()],
    );

    $this->assertStringContainsString(
      'code block',
      implode(' ', $errors),
    );
    $this->assertStringContainsString(
      'HTML entities',
      implode(' ', $errors),
    );
  }

  /**
   * Creates a source snippet containing redacted literals.
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
      redactions: 4,
    );
  }

}
