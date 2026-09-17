<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Collector;

use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\drupal_developer_assistant\Model\EntityTypeComponent;
use Drupal\drupal_developer_assistant\Resolver\SourcePathResolverInterface;

/**
 * Collects Drupal entity-type definitions.
 */
final class EntityTypeCollector implements CollectorInterface {

  /**
   * Constructs an entity-type collector.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly SourcePathResolverInterface $sourcePathResolver,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'entity_types';
  }

  /**
   * {@inheritdoc}
   */
  public function collect(): array {
    $definitions = $this->entityTypeManager->getDefinitions();
    ksort($definitions, SORT_STRING);

    $records = [];
    foreach ($definitions as $entity_type_id => $definition) {
      if (!$definition instanceof EntityTypeInterface) {
        throw new \UnexpectedValueException('The entity type manager must return EntityTypeInterface definitions.');
      }

      $entity_class = $definition->getClass();
      $records[] = new EntityTypeComponent(
        entityTypeId: $entity_type_id,
        label: (string) $definition->getLabel(),
        entityKind: $this->entityKind($definition),
        entityClass: is_string($entity_class) ? $entity_class : NULL,
        provider: $definition->getProvider(),
        baseTable: $definition->getBaseTable(),
        configPrefix: $definition instanceof ConfigEntityTypeInterface
          ? $definition->getConfigPrefix()
          : NULL,
        handlerClasses: $this->flattenHandlerClasses(
          $definition->getHandlerClasses(),
        ),
        sourcePath: $this->sourcePathResolver->resolve(
          is_string($entity_class) ? $entity_class : NULL,
        ),
      );
    }

    return $records;
  }

  /**
   * Determines an entity definition's broad storage kind.
   */
  private function entityKind(EntityTypeInterface $definition): string {
    if ($definition instanceof ContentEntityTypeInterface) {
      return 'content';
    }

    if ($definition instanceof ConfigEntityTypeInterface) {
      return 'configuration';
    }

    return 'other';
  }

  /**
   * Flattens nested handler groups into dot-separated identifiers.
   *
   * @param array<string, mixed> $handlers
   *   Entity handler definitions.
   * @param string $prefix
   *   The parent handler identifier during recursion.
   *
   * @return array<string, string>
   *   Handler classes keyed by flattened identifiers.
   */
  private function flattenHandlerClasses(
    array $handlers,
    string $prefix = '',
  ): array {
    $flattened = [];
    foreach ($handlers as $handler_id => $handler) {
      $qualified_id = $prefix === ''
        ? $handler_id
        : $prefix . '.' . $handler_id;

      if (is_string($handler)) {
        $flattened[$qualified_id] = $handler;
      }
      elseif (is_array($handler)) {
        $flattened += $this->flattenHandlerClasses($handler, $qualified_id);
      }
    }
    ksort($flattened, SORT_STRING);

    return $flattened;
  }

}
