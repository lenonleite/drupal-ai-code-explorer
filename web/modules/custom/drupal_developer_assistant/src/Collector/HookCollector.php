<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Collector;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\drupal_developer_assistant\Model\HookComponent;
use Drupal\drupal_developer_assistant\Resolver\SourcePathResolverInterface;

/**
 * Collects Drupal's compiled hook implementation map.
 */
final class HookCollector implements CollectorInterface {

  /**
   * Constructs a hook collector.
   */
  public function __construct(
    private readonly KeyValueFactoryInterface $keyValueFactory,
    private readonly SourcePathResolverInterface $sourcePathResolver,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'hooks';
  }

  /**
   * {@inheritdoc}
   */
  public function collect(): array {
    $hook_list = $this->keyValueFactory
      ->get('hook_data')
      ->get('hook_list', []);
    if (!is_array($hook_list)) {
      throw new \UnexpectedValueException('Drupal hook data must contain an array of hook implementations.');
    }
    ksort($hook_list, SORT_STRING);

    $records = [];
    foreach ($hook_list as $hook_name => $implementations) {
      if (!is_string($hook_name) || !is_array($implementations)) {
        throw new \UnexpectedValueException('Each hook must contain an implementation map.');
      }

      $execution_order = 0;
      foreach ($implementations as $callable => $provider) {
        if (!is_string($callable) || !is_string($provider)) {
          throw new \UnexpectedValueException('Hook implementations must map callable identifiers to module names.');
        }
        $execution_order++;

        if (str_contains($callable, '::')) {
          [$class_name, $method_name] = explode('::', $callable, 2);
          $implementation_type = 'object-oriented';
          $source_path = $this->sourcePathResolver->resolve($class_name);
        }
        else {
          $class_name = NULL;
          $method_name = NULL;
          $implementation_type = 'procedural';
          $source_path = $this->sourcePathResolver->resolveFunction($callable);
        }

        $records[] = new HookComponent(
          hookName: $hook_name,
          provider: $provider,
          implementationType: $implementation_type,
          callable: $callable,
          className: $class_name,
          methodName: $method_name,
          executionOrder: $execution_order,
          sourcePath: $source_path,
        );
      }
    }

    return $records;
  }

}
