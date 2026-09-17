<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Relationship;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\drupal_developer_assistant\Collector\EntityTypeCollector;
use Drupal\drupal_developer_assistant\Model\ComponentRelationship;
use Drupal\drupal_developer_assistant\Relationship\EntityRelationshipResolver;
use Drupal\drupal_developer_assistant\Resolver\SourcePathResolverInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests entity-type component relationships without booting Drupal.
 */
#[Group('drupal_developer_assistant')]
final class EntityRelationshipResolverTest extends UnitTestCase {

  /**
   * Tests provider, implementation, form, and handler relationships.
   */
  public function testResolvesEntityRelationships(): void {
    $entity_type = $this->createMock(ContentEntityTypeInterface::class);
    $entity_type->method('getLabel')->willReturn('Example');
    $entity_type->method('getClass')
      ->willReturn('Drupal\\example\\Entity\\Example');
    $entity_type->method('getProvider')->willReturn('example');
    $entity_type->method('getBaseTable')->willReturn('example');
    $entity_type->method('getHandlerClasses')->willReturn([
      'storage' => 'Drupal\\example\\ExampleStorage',
      'form' => [
        'edit' => 'Drupal\\example\\Form\\ExampleEditForm',
      ],
    ]);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getDefinitions')->willReturn([
      'example' => $entity_type,
    ]);

    $source_path_resolver = $this->createMock(
      SourcePathResolverInterface::class,
    );
    $source_path_resolver->method('resolve')->willReturnMap([
      [
        'Drupal\\example\\Entity\\Example',
        'modules/example/src/Entity/Example.php',
      ],
      [
        'Drupal\\example\\Form\\ExampleEditForm',
        'modules/example/src/Form/ExampleEditForm.php',
      ],
      [
        'Drupal\\example\\ExampleStorage',
        'modules/example/src/ExampleStorage.php',
      ],
    ]);

    $resolver = new EntityRelationshipResolver(
      new EntityTypeCollector($entity_type_manager, $source_path_resolver),
      $source_path_resolver,
    );

    $this->assertSame('entities', $resolver->id());
    $this->assertEquals([
      new ComponentRelationship(
        sourceType: 'entity_type',
        sourceId: 'example',
        relationship: 'provided_by',
        targetType: 'module',
        targetId: 'example',
        metadata: ['entity_kind' => 'content'],
      ),
      new ComponentRelationship(
        sourceType: 'entity_type',
        sourceId: 'example',
        relationship: 'implemented_by',
        targetType: 'entity_class',
        targetId: 'Drupal\\example\\Entity\\Example',
        metadata: [
          'entity_kind' => 'content',
          'source_path' => 'modules/example/src/Entity/Example.php',
        ],
      ),
      new ComponentRelationship(
        sourceType: 'entity_type',
        sourceId: 'example',
        relationship: 'uses_handler',
        targetType: 'form',
        targetId: 'Drupal\\example\\Form\\ExampleEditForm',
        metadata: [
          'handler' => 'form.edit',
          'source_path' => 'modules/example/src/Form/ExampleEditForm.php',
        ],
      ),
      new ComponentRelationship(
        sourceType: 'entity_type',
        sourceId: 'example',
        relationship: 'uses_handler',
        targetType: 'entity_handler',
        targetId: 'Drupal\\example\\ExampleStorage',
        metadata: [
          'handler' => 'storage',
          'source_path' => 'modules/example/src/ExampleStorage.php',
        ],
      ),
    ], $resolver->resolve());
  }

}
