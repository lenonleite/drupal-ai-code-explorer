<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Collector;

use Drupal\Core\Extension\InfoParserInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\drupal_developer_assistant\Model\ModuleComponent;

/**
 * Collects information about enabled Drupal modules.
 */
final class ModuleCollector implements CollectorInterface {

  /**
   * Constructs a module collector.
   */
  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly InfoParserInterface $infoParser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'modules';
  }

  /**
   * {@inheritdoc}
   */
  public function collect(): array {
    $modules = $this->moduleHandler->getModuleList();
    ksort($modules, SORT_STRING);

    $records = [];
    foreach ($modules as $machine_name => $extension) {
      $info = $this->infoParser->parse($extension->getPathname());

      $dependencies = array_map('strval', $info['dependencies'] ?? []);

      $records[] = new ModuleComponent(
        id: $machine_name,
        label: (string) ($info['name'] ?? $machine_name),
        sourcePath: $extension->getPath(),
        package: isset($info['package']) ? (string) $info['package'] : NULL,
        version: isset($info['version']) ? (string) $info['version'] : NULL,
        dependencies: array_values($dependencies),
        description: isset($info['description'])
          ? (string) $info['description']
          : NULL,
      );
    }

    return $records;
  }

}
