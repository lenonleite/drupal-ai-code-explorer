<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Model;

/**
 * Represents a Drupal hook implementation.
 */
final readonly class HookComponent extends Component {

  /**
   * Constructs a discovered hook implementation.
   *
   * @param string $hookName
   *   The hook name without the hook_ prefix.
   * @param string $provider
   *   The module on whose behalf the hook is implemented.
   * @param string $implementationType
   *   The implementation style: procedural or object-oriented.
   * @param string $callable
   *   The function or Class::method implementation identifier.
   * @param string|null $className
   *   The implementation class for an object-oriented hook.
   * @param string|null $methodName
   *   The implementation method for an object-oriented hook.
   * @param int $executionOrder
   *   The implementation's one-based execution position for this hook.
   * @param string|null $sourcePath
   *   The implementation file relative to the Drupal root, if known.
   */
  public function __construct(
    public string $hookName,
    public string $provider,
    public string $implementationType,
    public string $callable,
    public ?string $className,
    public ?string $methodName,
    public int $executionOrder,
    ?string $sourcePath,
  ) {
    parent::__construct(
      id: $hookName . ':' . $callable,
      type: 'hook',
      label: 'hook_' . $hookName,
      sourcePath: $sourcePath,
    );
  }

}
