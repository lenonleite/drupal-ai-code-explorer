<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Model;

/**
 * Represents an enabled Drupal module discovered by the inspector.
 */
final readonly class ModuleComponent extends Component {

  /**
   * Constructs a discovered module.
   *
   * @param string $id
   *   The module machine name.
   * @param string $label
   *   The human-readable module name.
   * @param string|null $sourcePath
   *   The module directory relative to the Drupal root, if known.
   * @param string|null $package
   *   The package declared by the module, if any.
   * @param string|null $version
   *   The module version, if any.
   * @param list<string> $dependencies
   *   Module dependencies declared in the module's info.yml file.
   * @param string|null $description
   *   The module description declared in its info.yml file, if any.
   */
  public function __construct(
    string $id,
    string $label,
    ?string $sourcePath,
    public ?string $package,
    public ?string $version,
    public array $dependencies,
    public ?string $description = NULL,
  ) {
    parent::__construct($id, 'module', $label, $sourcePath);
  }

}
