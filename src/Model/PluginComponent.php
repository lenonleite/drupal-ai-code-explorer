<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Model;

/**
 * Represents an individual plugin definition.
 */
readonly class PluginComponent extends Component {

  /**
   * Constructs a discovered plugin.
   *
   * @param string $pluginType
   *   The plugin type, such as block or action.
   * @param string $pluginId
   *   The plugin identifier within its plugin type.
   * @param string $label
   *   The human-readable plugin label.
   * @param string|null $className
   *   The plugin implementation class, if known.
   * @param string|null $provider
   *   The module or theme that provides the plugin, if known.
   * @param string|null $sourcePath
   *   The implementation file relative to the Drupal root, if known.
   */
  public function __construct(
    public string $pluginType,
    string $pluginId,
    string $label,
    public ?string $className,
    public ?string $provider,
    ?string $sourcePath,
  ) {
    parent::__construct(
      id: $pluginId,
      type: 'plugin',
      label: $label,
      sourcePath: $sourcePath,
    );
  }

}
