<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Relationship;

use Drupal\drupal_developer_assistant\Collector\ServiceCollector;
use Drupal\drupal_developer_assistant\Model\ComponentRelationship;
use Drupal\drupal_developer_assistant\Model\ServiceComponent;
use Drupal\drupal_developer_assistant\Resolver\SourcePathResolverInterface;

/**
 * Resolves service aliases, classes, and injected service dependencies.
 */
final class ServiceRelationshipResolver implements RelationshipResolverInterface {

  /**
   * Constructs a service relationship resolver.
   */
  public function __construct(
    private readonly ServiceCollector $serviceCollector,
    private readonly SourcePathResolverInterface $sourcePathResolver,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'services';
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(): array {
    $relationships = [];
    foreach ($this->serviceCollector->collect() as $service) {
      if (!$service instanceof ServiceComponent) {
        throw new \UnexpectedValueException('The service collector must return ServiceComponent objects.');
      }

      if ($service->aliasTarget !== NULL) {
        $relationships[] = new ComponentRelationship(
          sourceType: 'service',
          sourceId: $service->id,
          relationship: 'aliases',
          targetType: 'service',
          targetId: $service->aliasTarget,
        );
        continue;
      }

      if ($service->className !== NULL) {
        $metadata = [];
        $source_path = $this->sourcePathResolver->resolve(
          $service->className,
        );
        if ($source_path !== NULL) {
          $metadata['source_path'] = $source_path;
        }

        $relationships[] = new ComponentRelationship(
          sourceType: 'service',
          sourceId: $service->id,
          relationship: 'implemented_by',
          targetType: 'class',
          targetId: $service->className,
          metadata: $metadata,
        );
      }

      foreach ($service->references as $context => $service_ids) {
        foreach ($service_ids as $service_id) {
          $relationships[] = new ComponentRelationship(
            sourceType: 'service',
            sourceId: $service->id,
            relationship: 'depends_on',
            targetType: 'service',
            targetId: $service_id,
            metadata: ['context' => $context],
          );
        }
      }
    }

    return $relationships;
  }

}
