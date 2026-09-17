<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Model;

/**
 * Represents a plugin type and its manager service.
 */
final readonly class PluginManagerComponent extends Component {

  /**
   * Constructs a discovered plugin manager.
   *
   * @param string $pluginType
   *   The plugin type derived from the manager service identifier.
   * @param string $managerServiceId
   *   The plugin manager's service identifier.
   * @param string|null $className
   *   The plugin manager's configured PHP class, if known.
   */
  public function __construct(
    public string $pluginType,
    public string $managerServiceId,
    public ?string $className,
  ) {
    parent::__construct(
      id: $pluginType,
      type: 'plugin_manager',
      label: $pluginType,
      sourcePath: NULL,
    );
  }

}
