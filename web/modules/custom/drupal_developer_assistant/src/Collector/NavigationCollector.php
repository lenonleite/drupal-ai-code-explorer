<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Collector;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;
use Drupal\drupal_developer_assistant\Model\NavigationComponent;

/**
 * Collects menu links, local tasks, and local actions from Drupal YAML.
 */
final class NavigationCollector implements CollectorInterface {

  /**
   * Maps Drupal YAML suffixes to architecture component types.
   */
  private const array NAVIGATION_TYPES = [
    'links.menu' => 'menu_link',
    'links.task' => 'local_task',
    'links.action' => 'local_action',
  ];

  /**
   * Constructs a navigation collector.
   */
  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly string $appRoot,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'navigation';
  }

  /**
   * {@inheritdoc}
   */
  public function collect(): array {
    $directories = $this->moduleHandler->getModuleDirectories();
    $records = [];

    foreach (self::NAVIGATION_TYPES as $suffix => $navigation_type) {
      $definitions = (new YamlDiscovery($suffix, $directories))
        ->getDefinitions();
      ksort($definitions, SORT_STRING);

      foreach ($definitions as $definition_id => $definition) {
        if (!is_array($definition)) {
          throw new \UnexpectedValueException(sprintf(
            'Navigation definition "%s" must be an array.',
            $definition_id,
          ));
        }
        $provider = $this->stringValue($definition['provider'] ?? NULL);
        if ($provider === NULL || !isset($directories[$provider])) {
          continue;
        }

        $records[] = new NavigationComponent(
          definitionId: (string) $definition_id,
          navigationType: $navigation_type,
          label: $this->stringValue($definition['title'] ?? NULL)
            ?? (string) $definition_id,
          provider: $provider,
          routeName: $this->stringValue($definition['route_name'] ?? NULL),
          parentId: $this->parentId($navigation_type, $definition),
          baseRoute: $this->stringValue($definition['base_route'] ?? NULL),
          appearsOn: $this->stringList($definition['appears_on'] ?? []),
          menuName: $this->stringValue($definition['menu_name'] ?? NULL),
          sourcePath: $this->sourcePath(
            $directories[$provider],
            $provider,
            $suffix,
          ),
        );
      }
    }

    return $records;
  }

  /**
   * Extracts the type-specific parent definition ID.
   */
  private function parentId(string $navigation_type, array $definition): ?string {
    return match ($navigation_type) {
      'menu_link' => $this->stringValue($definition['parent'] ?? NULL),
      'local_task' => $this->stringValue($definition['parent_id'] ?? NULL),
      default => NULL,
    };
  }

  /**
   * Normalizes a scalar YAML value to a non-empty string.
   */
  private function stringValue(mixed $value): ?string {
    if (!is_scalar($value)) {
      return NULL;
    }

    $value = trim((string) $value);
    return $value === '' ? NULL : $value;
  }

  /**
   * Normalizes a YAML list to strings.
   *
   * @return list<string>
   *   Non-empty scalar values in input order.
   */
  private function stringList(mixed $values): array {
    if (!is_array($values)) {
      return [];
    }

    $strings = [];
    foreach ($values as $value) {
      $value = $this->stringValue($value);
      if ($value !== NULL) {
        $strings[] = $value;
      }
    }

    return $strings;
  }

  /**
   * Builds a portable path to the YAML definition file.
   */
  private function sourcePath(
    string $directory,
    string $provider,
    string $suffix,
  ): string {
    $path = str_replace(
      DIRECTORY_SEPARATOR,
      '/',
      rtrim($directory, DIRECTORY_SEPARATOR)
      . DIRECTORY_SEPARATOR
      . $provider
      . '.'
      . $suffix
      . '.yml',
    );
    $app_root = rtrim(
      str_replace(DIRECTORY_SEPARATOR, '/', $this->appRoot),
      '/',
    );
    if (str_starts_with($path, $app_root . '/')) {
      return substr($path, strlen($app_root) + 1);
    }

    return ltrim($path, '/');
  }

}
