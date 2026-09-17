<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Relationship;

use Drupal\drupal_developer_assistant\Collector\EntityTypeCollector;
use Drupal\drupal_developer_assistant\Model\ComponentRelationship;
use Drupal\drupal_developer_assistant\Model\EntityTypeComponent;
use Drupal\drupal_developer_assistant\Resolver\SourcePathResolverInterface;

/**
 * Resolves entity-type provider, class, and handler relationships.
 */
final class EntityRelationshipResolver implements RelationshipResolverInterface {

  /**
   * Constructs an entity relationship resolver.
   */
  public function __construct(
    private readonly EntityTypeCollector $entityTypeCollector,
    private readonly SourcePathResolverInterface $sourcePathResolver,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'entities';
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(): array {
    $relationships = [];
    foreach ($this->entityTypeCollector->collect() as $entity_type) {
      if (!$entity_type instanceof EntityTypeComponent) {
        throw new \UnexpectedValueException('The entity type collector must return EntityTypeComponent objects.');
      }

      if ($entity_type->provider !== NULL) {
        $relationships[] = new ComponentRelationship(
          sourceType: 'entity_type',
          sourceId: $entity_type->id,
          relationship: 'provided_by',
          targetType: 'module',
          targetId: $entity_type->provider,
          metadata: ['entity_kind' => $entity_type->entityKind],
        );
      }

      if ($entity_type->entityClass !== NULL) {
        $metadata = ['entity_kind' => $entity_type->entityKind];
        if ($entity_type->sourcePath !== NULL) {
          $metadata['source_path'] = $entity_type->sourcePath;
        }
        $relationships[] = new ComponentRelationship(
          sourceType: 'entity_type',
          sourceId: $entity_type->id,
          relationship: 'implemented_by',
          targetType: 'entity_class',
          targetId: $entity_type->entityClass,
          metadata: $metadata,
        );
      }

      foreach ($entity_type->handlerClasses as $handler_id => $handler_class) {
        $metadata = ['handler' => $handler_id];
        $source_path = $this->sourcePathResolver->resolve($handler_class);
        if ($source_path !== NULL) {
          $metadata['source_path'] = $source_path;
        }

        $relationships[] = new ComponentRelationship(
          sourceType: 'entity_type',
          sourceId: $entity_type->id,
          relationship: 'uses_handler',
          targetType: str_starts_with($handler_id, 'form.')
            ? 'form'
            : 'entity_handler',
          targetId: $handler_class,
          metadata: $metadata,
        );
      }
    }

    return $relationships;
  }

}
