<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Ai;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests the AI agent's grounding policy configuration.
 */
#[Group('drupal_developer_assistant')]
final class AgentGroundingConfigurationTest extends UnitTestCase {

  /**
   * Tests that source explanations remain grounded and explicit about gaps.
   */
  public function testSourceGroundingInstructions(): void {
    $path = dirname(__DIR__, 4)
      . '/config/optional/ai_agents.ai_agent.drupal_developer_assistant.yml';
    $configuration = Yaml::parseFile($path);
    $prompt = $configuration['system_prompt'];

    $this->assertStringContainsString('Confirmed by source', $prompt);
    $this->assertStringContainsString('Inferred', $prompt);
    $this->assertStringContainsString('Unavailable because redacted', $prompt);
    $this->assertStringContainsString(
      'Every implementation answer must use this Markdown evidence format',
      $prompt,
    );
    $this->assertStringContainsString(
      'Evidence: path:lines — symbol',
      $prompt,
    );
    $this->assertStringContainsString(
      'If any returned source snippet reports one or more redactions',
      $prompt,
    );
    $this->assertStringContainsString(
      'Never reconstruct, complete, or guess source code',
      $prompt,
    );
    $this->assertStringContainsString(
      'Do not include code blocks unless the developer explicitly asks to see code',
      $prompt,
    );
    $this->assertStringContainsString(
      'path, line numbers, and symbol',
      $prompt,
    );
    $this->assertStringContainsString(
      'Validate Grounded Answer is the mandatory final gate',
      $prompt,
    );
    $this->assertStringContainsString(
      'Inspect Drupal Component at most once',
      $prompt,
    );
    $this->assertStringContainsString(
      'If it returns no matches, ask for clarification and do not guess an ID',
      $prompt,
    );
    $this->assertSame(8, $configuration['max_loops']);
    $this->assertTrue(
      $configuration['tools']['drupal_developer_assistant:find_modules'],
    );
    $this->assertTrue(
      $configuration['tools']['drupal_developer_assistant:search_module_source'],
    );
    $this->assertTrue(
      $configuration['tools']['drupal_developer_assistant:validate_grounded_answer'],
    );
    $this->assertSame(
      1,
      $configuration['tool_settings']['drupal_developer_assistant:validate_grounded_answer']['return_directly'],
    );
    $this->assertSame(
      1,
      $configuration['tool_settings']['drupal_developer_assistant:validate_grounded_answer']['require_usage'],
    );
  }

}
