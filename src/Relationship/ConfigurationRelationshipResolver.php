<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Relationship;

use Drupal\drupal_developer_assistant\Collector\ConfigurationCollector;
use Drupal\drupal_developer_assistant\Model\ComponentRelationship;
use Drupal\drupal_developer_assistant\Model\ConfigurationComponent;

/**
 * Resolves dependencies declared by active configuration objects.
 */
final class ConfigurationRelationshipResolver implements RelationshipResolverInterface {

  /**
   * Constructs a configuration relationship resolver.
   */
  public function __construct(
    private readonly ConfigurationCollector $configurationCollector,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'configuration';
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(): array {
    $relationships = [];
    foreach ($this->configurationCollector->collect() as $configuration) {
      if (!$configuration instanceof ConfigurationComponent) {
        throw new \UnexpectedValueException('The configuration collector must return ConfigurationComponent objects.');
      }

      foreach ($configuration->dependencies as $dependency_type => $names) {
        $enforced = str_starts_with($dependency_type, 'enforced.');
        $base_type = $enforced
          ? substr($dependency_type, strlen('enforced.'))
          : $dependency_type;

        foreach ($names as $name) {
          $metadata = ['dependency_type' => $base_type];
          if ($enforced) {
            $metadata['enforced'] = 'true';
          }

          $relationships[] = new ComponentRelationship(
            sourceType: 'configuration',
            sourceId: $configuration->id,
            relationship: 'depends_on',
            targetType: $this->targetType($base_type),
            targetId: $name,
            metadata: $metadata,
          );
        }
      }
    }

    return $relationships;
  }

  /**
   * Maps Drupal's dependency group names to component types.
   */
  private function targetType(string $dependency_type): string {
    return match ($dependency_type) {
      'config' => 'configuration',
      'module', 'theme', 'content' => $dependency_type,
      default => 'configuration_dependency',
    };
  }

}
