<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Model;

/**
 * Represents a controller class referenced by Drupal routes.
 */
final readonly class ControllerComponent extends Component {

  /**
   * Constructs a discovered controller.
   *
   * @param string $className
   *   The fully qualified controller class name.
   * @param array<string, string> $routePaths
   *   Route paths keyed by route name.
   * @param array<string, string> $routeCallbacks
   *   Callback methods keyed by route name.
   * @param list<string> $serviceIds
   *   Service IDs that reference the controller class.
   * @param string|null $sourcePath
   *   The controller class file relative to the Drupal root, if known.
   */
  public function __construct(
    public string $className,
    public array $routePaths,
    public array $routeCallbacks,
    public array $serviceIds,
    ?string $sourcePath,
  ) {
    parent::__construct(
      id: $className,
      type: 'controller',
      label: basename(str_replace('\\', '/', $className)),
      sourcePath: $sourcePath,
    );
  }

}
