<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Model;

/**
 * Represents an individual Action plugin definition.
 */
final readonly class ActionPluginComponent extends PluginComponent {

  /**
   * Constructs a discovered Action plugin.
   *
   * @param string $pluginId
   *   The Action plugin identifier.
   * @param string $label
   *   The human-readable Action label.
   * @param string|null $className
   *   The Action implementation class, if known.
   * @param string|null $provider
   *   The module or core component that provides the Action, if known.
   * @param string|null $sourcePath
   *   The implementation file relative to the Drupal root, if known.
   * @param string|null $targetType
   *   The entity or system type to which the Action applies, if declared.
   */
  public function __construct(
    string $pluginId,
    string $label,
    ?string $className,
    ?string $provider,
    ?string $sourcePath,
    public ?string $targetType,
  ) {
    parent::__construct(
      pluginType: 'action',
      pluginId: $pluginId,
      label: $label,
      className: $className,
      provider: $provider,
      sourcePath: $sourcePath,
    );
  }

}
