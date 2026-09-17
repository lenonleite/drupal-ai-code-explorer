<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Model;

/**
 * Represents a form class referenced by Drupal routes.
 */
final readonly class FormComponent extends Component {

  /**
   * Constructs a discovered form.
   *
   * @param string $className
   *   The fully qualified form class name.
   * @param array<string, string> $routePaths
   *   Route paths keyed by route name.
   * @param array<string, string> $routeTypes
   *   Route target types keyed by route name.
   * @param array<string, string> $routeTargets
   *   Raw route targets keyed by route name.
   * @param string|null $sourcePath
   *   The form class file relative to the Drupal root, if known.
   */
  public function __construct(
    public string $className,
    public array $routePaths,
    public array $routeTypes,
    public array $routeTargets,
    ?string $sourcePath,
  ) {
    parent::__construct(
      id: $className,
      type: 'form',
      label: basename(str_replace('\\', '/', $className)),
      sourcePath: $sourcePath,
    );
  }

}
