<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\drupal_developer_assistant\Agent\AgentProgressLabeler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds meaningful labels to this agent's Explorer progress records.
 */
final readonly class AgentExplorerProgressSubscriber implements EventSubscriberInterface {

  /**
   * Constructs the progress subscriber.
   */
  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    private AgentProgressLabeler $progressLabeler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // The Explorer creates its generic decision record at the default priority.
    // Run later so that record can be relabelled without changing contrib code.
    return [
      PostGenerateResponseEvent::EVENT_NAME => ['relabelDecision', -100],
    ];
  }

  /**
   * Relabels the decision created for a Drupal Developer Assistant loop.
   */
  public function relabelDecision(PostGenerateResponseEvent $event): void {
    $tags = $event->getTags();
    if (!in_array('ai_agents', $tags, TRUE)
      || !in_array('ai_agents_prompt_drupal_developer_assistant', $tags, TRUE)
      || !$this->entityTypeManager->hasDefinition('ai_agent_decision')) {
      return;
    }

    $runner_id = $this->runnerId($tags);
    $normalized = $event->getOutput()->getNormalized();
    if ($runner_id === NULL || !$normalized instanceof ChatMessage) {
      return;
    }

    $tool_names = [];
    foreach ($normalized->getTools() ?? [] as $tool) {
      $tool_names[] = $tool->getName();
    }

    $storage = $this->entityTypeManager->getStorage('ai_agent_decision');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('runner_id', $runner_id)
      ->sort('id', 'DESC')
      ->range(0, 1)
      ->execute();
    if ($ids === []) {
      return;
    }

    $decision = $storage->load(reset($ids));
    if ($decision === NULL) {
      return;
    }

    $decision->set('label', $this->progressLabeler->labelForToolNames($tool_names));
    $decision->save();
  }

  /**
   * Extracts the runner identifier from AI Agents request tags.
   *
   * @param list<string> $tags
   *   Provider request tags.
   */
  private function runnerId(array $tags): ?string {
    $prefix = 'ai_agents_runner_';
    foreach ($tags as $tag) {
      if (str_starts_with($tag, $prefix)) {
        return substr($tag, strlen($prefix));
      }
    }

    return NULL;
  }

}
