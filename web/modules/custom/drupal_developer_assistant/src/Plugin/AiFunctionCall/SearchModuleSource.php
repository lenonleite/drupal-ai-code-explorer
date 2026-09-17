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
use Drupal\drupal_developer_assistant\Architecture\ModuleArchitectureBuilderInterface;
use Drupal\drupal_developer_assistant\Retrieval\SourceSnippet;
use Drupal\drupal_developer_assistant\Retrieval\SourceSnippetRetrieverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Retrieves bounded source evidence relevant to a module question.
 */
#[FunctionCall(
  id: 'drupal_developer_assistant:search_module_source',
  function_name: 'drupal_developer_assistant_search_module_source',
  name: 'Search Drupal Module Source',
  description: 'Searches one enabled Drupal module for PHP declarations relevant to a developer question. Returns bounded, redacted source snippets with paths, symbols, lines, and lexical relevance metadata.',
  group: 'information_tools',
  module_dependencies: ['drupal_developer_assistant'],
  context_definitions: [
    'module_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Module machine name'),
      description: new TranslatableMarkup('The enabled Drupal module machine name, for example automated_cron.'),
      required: TRUE,
    ),
    'question' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Developer question'),
      description: new TranslatableMarkup('A focused question describing the implementation behavior or source symbol to find.'),
      required: TRUE,
    ),
  ],
)]
final class SearchModuleSource extends FunctionCallBase implements ExecutableFunctionCallInterface {

  /**
   * Constructs a Search Module Source function call plugin.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ContextDefinitionNormalizer $context_definition_normalizer,
    AiDataTypeConverterPluginManager $data_type_converter_manager,
    private readonly ModuleArchitectureBuilderInterface $architectureBuilder,
    private readonly SourceSnippetRetrieverInterface $sourceRetriever,
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
      $container->get(ModuleArchitectureBuilderInterface::class),
      $container->get(SourceSnippetRetrieverInterface::class),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL) {
    if (!$this->currentUser->hasPermission('access drupal developer assistant')) {
      throw new \RuntimeException('You do not have permission to search Drupal modules.');
    }
    if (!$this->currentUser->hasPermission('view drupal developer assistant source')) {
      throw new \RuntimeException('You do not have permission to view Drupal source evidence.');
    }

    $module_id = trim((string) $this->getContextValue('module_id'));
    $question = trim((string) $this->getContextValue('question'));
    if (
      !preg_match('/^[a-z0-9_]+$/', $module_id)
      || $question === ''
      || mb_strlen($question, 'UTF-8') > 1_000
    ) {
      $this->storeOutput([
        'tool' => 'search_module_source',
        'status' => 'error',
        'message' => 'The module machine name or developer question is invalid.',
      ]);
      return;
    }

    $architecture = $this->architectureBuilder->build($module_id);
    if ($architecture === NULL) {
      $this->storeOutput([
        'tool' => 'search_module_source',
        'status' => 'error',
        'module_id' => $module_id,
        'message' => 'The requested module is not enabled.',
      ]);
      return;
    }

    $snippets = $this->sourceRetriever->retrieve($architecture, $question);
    $results = array_map(
      static fn(SourceSnippet $snippet): array => $snippet->jsonSerialize(),
      $snippets,
    );
    $selection_reasons = array_column($results, 'selection_reason');
    $truncation_flags = array_column($results, 'truncated');
    $this->storeOutput([
      'tool' => 'search_module_source',
      'status' => 'success',
      'module' => [
        'id' => $architecture->module->id,
        'name' => $architecture->module->label,
      ],
      'question' => $question,
      'summary' => [
        'strategy' => 'bounded_lexical_retrieval',
        'snippet_count' => count($results),
        'fallback_used' => in_array(
          'production_fallback',
          $selection_reasons,
          TRUE,
        ),
        'truncated' => in_array(TRUE, $truncation_flags, TRUE),
      ],
      'snippets' => $results,
    ]);
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
