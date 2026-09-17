<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Agent;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatMessage;

/**
 * Repairs an invalid answer through Drupal AI's default chat provider.
 */
final readonly class GroundedAnswerRepairer implements GroundedAnswerRepairerInterface {

  /**
   * Constructs a grounded-answer repairer.
   */
  public function __construct(
    private AiProviderPluginManager $providerManager,
    private GroundedAnswerRepairInputBuilder $inputBuilder,
    private GroundedAnswerRepairResponseParser $responseParser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function repair(
    string $question,
    string $candidate,
    array $validation_errors,
    string $module_path,
    array $snippets,
  ): string {
    $defaults = $this->providerManager->getDefaultProviderForOperationType('chat');
    $provider_id = $defaults['provider_id'] ?? NULL;
    $model_id = $defaults['model_id'] ?? NULL;
    if (!is_string($provider_id) || $provider_id === '' || !is_string($model_id) || $model_id === '') {
      throw new \RuntimeException('No default Drupal AI chat provider and model are configured for answer repair.');
    }
    $output = $this->providerManager
      ->createInstance($provider_id)
      ->chat(
        $this->inputBuilder->build(
          $question,
          $candidate,
          $validation_errors,
          $module_path,
          $snippets,
        ),
        $model_id,
        ['drupal_developer_assistant', 'agent_answer_repair'],
      );
    $message = $output->getNormalized();
    if (!$message instanceof ChatMessage) {
      throw new \UnexpectedValueException('The answer repair provider returned an unexpected streaming response.');
    }
    return $this->responseParser->parse(
      $message->getText(),
      $module_path,
      $snippets,
    );
  }

}
