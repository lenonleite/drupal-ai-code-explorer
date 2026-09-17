<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Collector;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\drupal_developer_assistant\Model\PluginComponent;
use Drupal\drupal_developer_assistant\Resolver\SourcePathResolverInterface;

/**
 * Collects individual Block plugin definitions.
 */
final class BlockPluginCollector implements CollectorInterface {

  /**
   * Constructs a Block plugin collector.
   */
  public function __construct(
    private readonly BlockManagerInterface $blockManager,
    private readonly SourcePathResolverInterface $sourcePathResolver,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'plugins.block';
  }

  /**
   * {@inheritdoc}
   */
  public function collect(): array {
    $definitions = $this->blockManager->getDefinitions();
    ksort($definitions, SORT_STRING);

    $records = [];
    foreach ($definitions as $plugin_id => $definition) {
      if (!is_array($definition)) {
        throw new \UnexpectedValueException('The Block plugin manager must return array definitions.');
      }

      $class_name = isset($definition['class']) && is_string($definition['class'])
        ? $definition['class']
        : NULL;

      $records[] = new PluginComponent(
        pluginType: 'block',
        pluginId: $plugin_id,
        label: isset($definition['admin_label'])
          ? (string) $definition['admin_label']
          : $plugin_id,
        className: $class_name,
        provider: isset($definition['provider']) && is_string($definition['provider'])
          ? $definition['provider']
          : NULL,
        sourcePath: $this->sourcePathResolver->resolve($class_name),
      );
    }

    return $records;
  }

}
