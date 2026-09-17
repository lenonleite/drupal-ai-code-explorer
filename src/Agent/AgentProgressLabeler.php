<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Agent;

/**
 * Translates agent tool calls into human-readable progress labels.
 */
final readonly class AgentProgressLabeler {

  /**
   * Labels the decision represented by one or more tool calls.
   *
   * @param list<string> $tool_names
   *   Function names selected by the agent in the current loop.
   *
   * @return string
   *   A short label suitable for the AI Agents Explorer progress table.
   */
  public function labelForToolNames(array $tool_names): string {
    if ($tool_names === []) {
      return 'Agent understands the question and plans the investigation';
    }

    $labels = [];
    foreach ($tool_names as $tool_name) {
      $labels[] = match ($tool_name) {
        'drupal_developer_assistant_find_modules' => 'Agent finds enabled modules',
        'drupal_developer_assistant_inspect_module' => 'Agent gathers module architecture',
        'drupal_developer_assistant_inspect_component' => 'Agent inspects component details',
        'drupal_developer_assistant_search_module_source' => 'Agent searches source evidence',
        'drupal_developer_assistant_validate_grounded_answer' => 'Agent validates the final answer and repairs it if needed',
        default => 'Agent chooses the next investigation step',
      };
    }

    return implode(' + ', array_values(array_unique($labels)));
  }

}
