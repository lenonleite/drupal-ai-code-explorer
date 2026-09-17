<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Collector;

use Drupal\Core\Action\ActionManager;
use Drupal\drupal_developer_assistant\Model\ActionPluginComponent;
use Drupal\drupal_developer_assistant\Resolver\SourcePathResolverInterface;

/**
 * Collects individual Action plugin definitions.
 */
final class ActionPluginCollector implements CollectorInterface {

  /**
   * Constructs an Action plugin collector.
   */
  public function __construct(
    private readonly ActionManager $actionManager,
    private readonly SourcePathResolverInterface $sourcePathResolver,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'plugins.action';
  }

  /**
   * {@inheritdoc}
   */
  public function collect(): array {
    $definitions = $this->actionManager->getDefinitions();
    ksort($definitions, SORT_STRING);

    $records = [];
    foreach ($definitions as $plugin_id => $definition) {
      if (!is_array($definition)) {
        throw new \UnexpectedValueException('The Action plugin manager must return array definitions.');
      }

      $class_name = isset($definition['class']) && is_string($definition['class'])
        ? $definition['class']
        : NULL;

      $records[] = new ActionPluginComponent(
        pluginId: $plugin_id,
        label: isset($definition['label'])
          ? (string) $definition['label']
          : $plugin_id,
        className: $class_name,
        provider: isset($definition['provider']) && is_string($definition['provider'])
          ? $definition['provider']
          : NULL,
        sourcePath: $this->sourcePathResolver->resolve($class_name),
        targetType: isset($definition['type']) && is_string($definition['type'])
          ? $definition['type']
          : NULL,
      );
    }

    return $records;
  }

}
