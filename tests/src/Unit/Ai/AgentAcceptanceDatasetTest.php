<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Ai;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests the agent acceptance cases against its orchestration contract.
 */
#[Group('drupal_developer_assistant')]
final class AgentAcceptanceDatasetTest extends UnitTestCase {

  /**
   * Tests every acceptance case is bounded and supported by the agent.
   */
  public function testAcceptanceCasesMatchAgentConfiguration(): void {
    $module_path = dirname(__DIR__, 4);
    $agent = Yaml::parseFile(
      $module_path . '/config/optional/ai_agents.ai_agent.drupal_developer_assistant.yml',
    );
    $files = glob($module_path . '/evaluations/agent/*.yml');
    $this->assertNotFalse($files);
    $this->assertCount(4, $files);

    foreach ($files as $file) {
      $case = Yaml::parseFile($file);
      $this->assertIsArray($case);
      $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $case['id']);
      $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $case['module']);
      $this->assertNotSame('', trim($case['question']));

      $sequence = $case['expected']['tool_sequence'];
      $inspect_position = array_search(
        'drupal_developer_assistant_inspect_module',
        $sequence,
        TRUE,
      );
      $this->assertNotFalse(
        $inspect_position,
        $case['id'] . ' must gather module evidence.',
      );
      if (in_array('drupal_developer_assistant_find_modules', $sequence, TRUE)) {
        $this->assertSame(
          'drupal_developer_assistant_find_modules',
          $sequence[0],
          $case['id'] . ' must discover the exact enabled module first.',
        );
        $this->assertSame(1, $inspect_position);
      }
      else {
        $this->assertSame(0, $inspect_position);
      }
      $this->assertSame(
        'drupal_developer_assistant_validate_grounded_answer',
        $sequence[array_key_last($sequence)],
        $case['id'] . ' must finish through the validation gate.',
      );
      $this->assertSame(
        $sequence,
        array_values(array_unique($sequence)),
        $case['id'] . ' must not repeat an evidence tool.',
      );
      $this->assertLessThanOrEqual($agent['max_loops'], count($sequence));

      foreach ($sequence as $function_name) {
        $plugin_id = str_replace(
          'drupal_developer_assistant_',
          'drupal_developer_assistant:',
          $function_name,
        );
        $this->assertTrue(
          $agent['tools'][$plugin_id] ?? FALSE,
          sprintf('%s requires enabled tool %s.', $case['id'], $plugin_id),
        );
      }

      $this->assertContains(
        '### Confirmed by source',
        $case['expected']['required_answer_headings'],
      );
      $this->assertNotEmpty($case['expected']['required_evidence_paths']);
    }

    $gate = $agent['tool_settings']['drupal_developer_assistant:validate_grounded_answer'];
    $this->assertSame(1, $gate['require_usage']);
    $this->assertSame(1, $gate['return_directly']);
  }

}
