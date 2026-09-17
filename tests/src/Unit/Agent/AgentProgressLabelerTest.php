<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Agent;

use Drupal\drupal_developer_assistant\Agent\AgentProgressLabeler;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests readable AI Agent Explorer progress labels.
 */
#[Group('drupal_developer_assistant')]
final class AgentProgressLabelerTest extends UnitTestCase {

  /**
   * Tests labels for known agent steps and fallback states.
   *
   * @param list<string> $tool_names
   *   Tool function names from an agent decision.
   * @param string $expected
   *   Expected progress label.
   */
  #[DataProvider('labelCases')]
  public function testLabels(array $tool_names, string $expected): void {
    $labeler = new AgentProgressLabeler();

    $this->assertSame($expected, $labeler->labelForToolNames($tool_names));
  }

  /**
   * Provides tool calls and their expected labels.
   *
   * @return iterable<string, array{list<string>, string}>
   *   Label cases keyed by their purpose.
   */
  public static function labelCases(): iterable {
    yield 'module discovery' => [
      ['drupal_developer_assistant_find_modules'],
      'Agent finds enabled modules',
    ];
    yield 'module evidence' => [
      ['drupal_developer_assistant_inspect_module'],
      'Agent gathers module architecture',
    ];
    yield 'component evidence' => [
      ['drupal_developer_assistant_inspect_component'],
      'Agent inspects component details',
    ];
    yield 'source evidence' => [
      ['drupal_developer_assistant_search_module_source'],
      'Agent searches source evidence',
    ];
    yield 'validation' => [
      ['drupal_developer_assistant_validate_grounded_answer'],
      'Agent validates the final answer and repairs it if needed',
    ];
    yield 'drafting' => [
      [],
      'Agent understands the question and plans the investigation',
    ];
    yield 'unknown tool' => [
      ['another_tool'],
      'Agent chooses the next investigation step',
    ];
    yield 'multiple tools' => [
      [
        'drupal_developer_assistant_inspect_module',
        'drupal_developer_assistant_search_module_source',
      ],
      'Agent gathers module architecture + Agent searches source evidence',
    ];
  }

}
