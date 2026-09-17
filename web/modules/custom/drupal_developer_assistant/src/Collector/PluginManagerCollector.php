<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Collector;

use Drupal\drupal_developer_assistant\Model\PluginManagerComponent;
use Drupal\drupal_developer_assistant\Model\ServiceComponent;

/**
 * Collects plugin managers from Drupal's service definitions.
 */
final class PluginManagerCollector implements CollectorInterface {

  /**
   * The service identifier prefix used by Drupal's plugin manager pass.
   */
  private const string SERVICE_ID_PREFIX = 'plugin.manager.';

  /**
   * Constructs a plugin manager collector.
   */
  public function __construct(
    private readonly ServiceCollector $serviceCollector,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'plugin_managers';
  }

  /**
   * {@inheritdoc}
   */
  public function collect(): array {
    $records = [];
    foreach ($this->serviceCollector->collect() as $service) {
      if (!$service instanceof ServiceComponent) {
        throw new \UnexpectedValueException('The service collector must return ServiceComponent objects.');
      }

      if (
        $service->aliasTarget !== NULL
        || !str_starts_with($service->id, self::SERVICE_ID_PREFIX)
      ) {
        continue;
      }

      $records[] = new PluginManagerComponent(
        pluginType: substr($service->id, strlen(self::SERVICE_ID_PREFIX)),
        managerServiceId: $service->id,
        className: $service->className,
      );
    }

    return $records;
  }

}
