<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Model;

/**
 * Represents a navigation definition discovered from Drupal YAML.
 */
final readonly class NavigationComponent extends Component {

  /**
   * Constructs a discovered navigation definition.
   *
   * @param string $definitionId
   *   The navigation plugin definition ID.
   * @param string $navigationType
   *   The Drupal navigation type: menu_link, local_task, or local_action.
   * @param string $label
   *   The human-readable navigation title.
   * @param string $provider
   *   The module whose YAML file provides the definition.
   * @param string|null $routeName
   *   The destination route, when declared.
   * @param string|null $parentId
   *   The parent menu-link or local-task definition ID, when declared.
   * @param string|null $baseRoute
   *   The local task's base route, when declared.
   * @param list<string> $appearsOn
   *   Routes on which a local action appears.
   * @param string|null $menuName
   *   The menu name for a menu link, when declared.
   * @param string $sourcePath
   *   The YAML file relative to the Drupal root.
   */
  public function __construct(
    string $definitionId,
    public string $navigationType,
    string $label,
    public string $provider,
    public ?string $routeName,
    public ?string $parentId,
    public ?string $baseRoute,
    public array $appearsOn,
    public ?string $menuName,
    string $sourcePath,
  ) {
    parent::__construct(
      id: $definitionId,
      type: $navigationType,
      label: $label,
      sourcePath: $sourcePath,
    );
  }

}
