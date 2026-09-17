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
use Drupal\drupal_developer_assistant\Agent\GroundedAnswerRepairerInterface;
use Drupal\drupal_developer_assistant\Agent\GroundedAnswerValidatorInterface;
use Drupal\drupal_developer_assistant\Architecture\ModuleArchitectureBuilderInterface;
use Drupal\drupal_developer_assistant\Retrieval\SourceSnippetRetrieverInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Validates an agent answer and performs at most one repair request.
 */
#[FunctionCall(
  id: 'drupal_developer_assistant:validate_grounded_answer',
  function_name: 'drupal_developer_assistant_validate_grounded_answer',
  name: 'Validate Grounded Answer',
  description: 'Final answer gate. Submit the complete drafted answer, original module ID, and original question only after evidence tools have run. It validates citations and redactions, makes at most one AI repair request when invalid, and returns the safe final answer directly.',
  group: 'information_tools',
  module_dependencies: ['drupal_developer_assistant'],
  context_definitions: [
    'module_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Module machine name'),
      description: new TranslatableMarkup('The same enabled Drupal module machine name used by the evidence tools.'),
      required: TRUE,
    ),
    'question' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Original developer question'),
      description: new TranslatableMarkup('The original developer question, unchanged.'),
      required: TRUE,
    ),
    'answer' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Complete drafted answer'),
      description: new TranslatableMarkup('The complete Markdown answer to validate. Do not send an outline or commentary.'),
      required: TRUE,
    ),
  ],
)]
final class ValidateGroundedAnswer extends FunctionCallBase implements ExecutableFunctionCallInterface {

  /**
   * Constructs a Validate Grounded Answer function call plugin.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ContextDefinitionNormalizer $context_definition_normalizer,
    AiDataTypeConverterPluginManager $data_type_converter_manager,
    private readonly ModuleArchitectureBuilderInterface $architectureBuilder,
    private readonly SourceSnippetRetrieverInterface $sourceRetriever,
    private readonly GroundedAnswerValidatorInterface $validator,
    private readonly GroundedAnswerRepairerInterface $repairer,
    private readonly AccountProxyInterface $currentUser,
    private readonly LoggerInterface $logger,
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
      $container->get(GroundedAnswerValidatorInterface::class),
      $container->get(GroundedAnswerRepairerInterface::class),
      $container->get('current_user'),
      $container->get('logger.channel.drupal_developer_assistant'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL) {
    if (!$this->currentUser->hasPermission('access drupal developer assistant')) {
      throw new \RuntimeException('You do not have permission to inspect Drupal modules.');
    }
    if (!$this->currentUser->hasPermission('view drupal developer assistant source')) {
      throw new \RuntimeException('You do not have permission to validate source evidence.');
    }

    $module_id = trim((string) $this->getContextValue('module_id'));
    $question = trim((string) $this->getContextValue('question'));
    $answer = trim((string) $this->getContextValue('answer'));
    if (
      !preg_match('/^[a-z0-9_]+$/', $module_id)
      || $question === ''
      || mb_strlen($question, 'UTF-8') > 1_000
      || $answer === ''
      || strlen($answer) > 20_000
    ) {
      $this->storeFailure('The module, question, or drafted answer is invalid.');
      return;
    }

    $architecture = $this->architectureBuilder->build($module_id);
    if ($architecture === NULL || $architecture->module->sourcePath === NULL) {
      $this->storeFailure('The requested enabled module or its source path is unavailable.');
      return;
    }
    $snippets = $this->sourceRetriever->retrieve($architecture, $question);
    if ($snippets === []) {
      $this->storeFailure('No source evidence was available to validate the answer.');
      return;
    }

    $errors = $this->validator->validate(
      $answer,
      $question,
      $architecture->module->sourcePath,
      $snippets,
    );
    if ($errors === []) {
      $this->storeSuccess($answer, FALSE);
      return;
    }

    try {
      $repaired = $this->repairer->repair(
        $question,
        $answer,
        $errors,
        $architecture->module->sourcePath,
        $snippets,
      );
    }
    catch (\Throwable $exception) {
      $this->logger->error(
        'Grounded agent answer repair failed with exception "@exception": @message',
        [
          '@exception' => $exception::class,
          '@message' => $exception->getMessage(),
        ],
      );
      $this->storeFailure(
        'The drafted answer failed validation and its single repair request could not be completed.',
        $errors,
        TRUE,
      );
      return;
    }

    $repair_errors = $this->validator->validate(
      $repaired,
      $question,
      $architecture->module->sourcePath,
      $snippets,
    );
    if ($repair_errors !== []) {
      $this->logger->warning(
        'Grounded agent answer remained invalid after its single repair attempt: @errors',
        ['@errors' => implode(' | ', $repair_errors)],
      );
      $this->storeFailure(
        'I could not produce a grounded answer after one repair attempt.',
        $repair_errors,
        TRUE,
      );
      return;
    }
    $this->storeSuccess($repaired, TRUE);
  }

  /**
   * Stores a validated final answer for direct return by AI Agents.
   */
  private function storeSuccess(string $answer, bool $repaired): void {
    $this->setStructuredOutput([
      'tool' => 'validate_grounded_answer',
      'status' => 'success',
      'repaired' => $repaired,
    ]);
    $this->setOutput($answer);
  }

  /**
   * Stores a safe failure without exposing an unvalidated draft.
   */
  private function storeFailure(
    string $message,
    array $validation_errors = [],
    bool $repair_attempted = FALSE,
  ): void {
    $this->setStructuredOutput([
      'tool' => 'validate_grounded_answer',
      'status' => 'error',
      'repaired' => $repair_attempted,
      'message' => $message,
      'validation_errors' => $validation_errors,
    ]);
    $this->setOutput($message);
  }

}
