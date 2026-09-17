<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Model;

/**
 * Represents a compiled Drupal route.
 */
final readonly class RouteComponent extends Component {

  /**
   * Constructs a discovered route.
   *
   * @param string $routeName
   *   The unique route name.
   * @param string $path
   *   The route path pattern.
   * @param list<string> $methods
   *   Allowed HTTP methods.
   * @param string|null $targetType
   *   The execution target type, such as controller or entity_form.
   * @param string|null $target
   *   The raw execution target from the compiled route.
   * @param string|null $targetClass
   *   The resolved controller, form, or entity-handler class.
   * @param array<string, string> $requirements
   *   Route requirements keyed by requirement name.
   * @param bool $adminRoute
   *   Whether Drupal treats this as an administration route.
   * @param string|null $sourcePath
   *   The target class file relative to the Drupal root, if known.
   */
  public function __construct(
    string $routeName,
    string $path,
    public array $methods,
    public ?string $targetType,
    public ?string $target,
    public ?string $targetClass,
    public array $requirements,
    public bool $adminRoute,
    ?string $sourcePath,
  ) {
    parent::__construct(
      id: $routeName,
      type: 'route',
      label: $path,
      sourcePath: $sourcePath,
    );
  }

}
