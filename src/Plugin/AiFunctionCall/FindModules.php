<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Plugin\AiFunctionCall;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\PluginManager\AiDataTypeConverterPluginManager;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai\Utility\ContextDefinitionNormalizer;
use Drupal\drupal_developer_assistant\Collector\ModuleCollector;
use Drupal\drupal_developer_assistant\Model\ModuleComponent;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Finds exact enabled module IDs from a short natural-language query.
 */
#[FunctionCall(
  id: 'drupal_developer_assistant:find_modules',
  function_name: 'drupal_developer_assistant_find_modules',
  name: 'Find Drupal Modules',
  description: 'Searches enabled Drupal modules by machine name, name, description, package, path, and dependencies. Use this before Inspect Drupal Module when the question does not contain an exact module machine name.',
  group: 'information_tools',
  module_dependencies: ['drupal_developer_assistant'],
  context_definitions: [
    'query' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Module search query'),
      description: new TranslatableMarkup('Short module name or functionality terms extracted from the developer question, for example cron or path alias.'),
      required: TRUE,
    ),
  ],
)]
final class FindModules extends FunctionCallBase implements ExecutableFunctionCallInterface {

  /**
   * Maximum number of module candidates returned to the model.
   */
  private const int MAX_RESULTS = 10;

  /**
   * Words that do not help distinguish one Drupal module from another.
   */
  private const array STOP_WORDS = [
    'about',
    'and',
    'can',
    'does',
    'from',
    'drupal',
    'explain',
    'find',
    'for',
    'how',
    'into',
    'is',
    'module',
    'modules',
    'please',
    'of',
    'on',
    'show',
    'tell',
    'that',
    'the',
    'this',
    'to',
    'use',
    'used',
    'uses',
    'what',
    'which',
    'with',
    'work',
    'works',
    'you',
  ];

  /**
   * Constructs a Find Drupal Modules function call plugin.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ContextDefinitionNormalizer $context_definition_normalizer,
    AiDataTypeConverterPluginManager $data_type_converter_manager,
    private readonly ModuleCollector $moduleCollector,
    private readonly AccountProxyInterface $currentUser,
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $context_definition_normalizer,
      $data_type_converter_manager,
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface|static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.context_definition_normalizer'),
      $container->get('plugin.manager.ai_data_type_converter'),
      $container->get(ModuleCollector::class),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL) {
    if (!$this->currentUser->hasPermission('access drupal developer assistant')) {
      throw new \RuntimeException('You do not have permission to find Drupal modules.');
    }

    $query = trim((string) $this->getContextValue('query'));
    if ($query === '' || mb_strlen($query, 'UTF-8') > 200) {
      $this->storeOutput([
        'tool' => 'find_modules',
        'status' => 'error',
        'message' => 'The module search query must contain between 1 and 200 characters.',
      ]);
      return;
    }

    $terms = $this->terms($query);
    if ($terms === []) {
      $this->storeOutput([
        'tool' => 'find_modules',
        'status' => 'error',
        'message' => 'The module search query did not contain a specific module or functionality term.',
      ]);
      return;
    }

    $modules = $this->moduleCollector->collect();
    $matches = [];
    foreach ($modules as $module) {
      if (!$module instanceof ModuleComponent) {
        continue;
      }
      $match = $this->match($module, $query, $terms);
      if ($match !== NULL) {
        $matches[] = $match;
      }
    }
    usort(
      $matches,
      static function (array $first, array $second): int {
        $score_order = $second['score'] <=> $first['score'];
        return $score_order !== 0
          ? $score_order
          : strcmp($first['module']->id, $second['module']->id);
      },
    );

    $selected = array_slice($matches, 0, self::MAX_RESULTS);
    $this->storeOutput([
      'tool' => 'find_modules',
      'status' => 'success',
      'query' => $query,
      'summary' => [
        'enabled_modules_searched' => count($modules),
        'matches_found' => count($matches),
        'matches_returned' => count($selected),
        'truncated' => count($matches) > self::MAX_RESULTS,
      ],
      'matches' => array_map(
        $this->outputMatch(...),
        $selected,
      ),
    ]);
  }

  /**
   * Extracts meaningful lowercase search terms from the query.
   *
   * @return list<string>
   *   Unique query terms in their original order.
   */
  private function terms(string $query): array {
    $parts = preg_split(
      '/[^\p{L}\p{N}]+/u',
      mb_strtolower($query, 'UTF-8'),
      flags: PREG_SPLIT_NO_EMPTY,
    ) ?: [];
    $terms = array_filter(
      $parts,
      static fn(string $term): bool => mb_strlen($term, 'UTF-8') > 1
        && !in_array($term, self::STOP_WORDS, TRUE),
    );
    return array_values(array_unique($terms));
  }

  /**
   * Scores one enabled module against the search terms.
   *
   * @param \Drupal\drupal_developer_assistant\Model\ModuleComponent $module
   *   Enabled module to score.
   * @param string $query
   *   Original module search query.
   * @param list<string> $terms
   *   Meaningful terms extracted from the query.
   *
   * @return array{score: int, module: \Drupal\drupal_developer_assistant\Model\ModuleComponent, matched_fields: list<string>}|null
   *   Ranked match metadata, or NULL when no field matched.
   */
  private function match(
    ModuleComponent $module,
    string $query,
    array $terms,
  ): ?array {
    $fields = [
      'id' => [$module->id, 100],
      'name' => [$module->label, 90],
      'description' => [$module->description ?? '', 60],
      'dependencies' => [implode(' ', $module->dependencies), 35],
      'package' => [$module->package ?? '', 20],
      'path' => [$module->sourcePath ?? '', 10],
    ];
    $score = 0;
    $matched_fields = [];
    foreach ($fields as $name => [$value, $weight]) {
      $normalized_value = $this->normalizeSearchText($value);
      foreach ($terms as $term) {
        if (str_contains($normalized_value, $term)) {
          $score += $weight;
          $matched_fields[$name] = TRUE;
        }
      }
    }

    $normalized_query = $this->normalizeSearchText($query);
    if ($normalized_query === $this->normalizeSearchText($module->id)) {
      $score += 1_000;
      $matched_fields['id'] = TRUE;
    }
    if ($normalized_query === $this->normalizeSearchText($module->label)) {
      $score += 900;
      $matched_fields['name'] = TRUE;
    }
    if ($score === 0) {
      return NULL;
    }

    return [
      'score' => $score,
      'module' => $module,
      'matched_fields' => array_keys($matched_fields),
    ];
  }

  /**
   * Normalizes one value for deterministic case-insensitive matching.
   */
  private function normalizeSearchText(string $value): string {
    return trim((string) preg_replace(
      '/[^\p{L}\p{N}]+/u',
      ' ',
      mb_strtolower($value, 'UTF-8'),
    ));
  }

  /**
   * Converts one internal ranked result into bounded tool output.
   *
   * @param array{score: int, module: \Drupal\drupal_developer_assistant\Model\ModuleComponent, matched_fields: list<string>} $match
   *   Internal ranked module result.
   *
   * @return array<string, mixed>
   *   Model-readable enabled module candidate.
   */
  private function outputMatch(array $match): array {
    $module = $match['module'];
    return [
      'id' => $module->id,
      'name' => $module->label,
      'description' => $module->description === NULL
        ? NULL
        : mb_strcut($module->description, 0, 500, 'UTF-8'),
      'package' => $module->package,
      'path' => $module->sourcePath,
      'dependencies' => array_slice($module->dependencies, 0, 20),
      'score' => $match['score'],
      'matched_fields' => $match['matched_fields'],
    ];
  }

  /**
   * Stores both the model-readable and structured forms of a tool result.
   *
   * @param array<string, mixed> $output
   *   The tool result.
   */
  private function storeOutput(array $output): void {
    $this->setStructuredOutput($output);
    $this->setOutput((string) json_encode(
      $output,
      JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    ));
  }

}
