<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Collector;

use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\drupal_developer_assistant\Collector\EntityTypeCollector;
use Drupal\drupal_developer_assistant\Model\EntityTypeComponent;
use Drupal\drupal_developer_assistant\Resolver\SourcePathResolverInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests entity-type definition normalization without booting Drupal.
 */
#[Group('drupal_developer_assistant')]
final class EntityTypeCollectorTest extends UnitTestCase {

  /**
   * Tests content and configuration entity-type records.
   */
  public function testCollectsEntityTypes(): void {
    $content = $this->createMock(ContentEntityTypeInterface::class);
    $content->method('getLabel')->willReturn('Alpha content');
    $content->method('getClass')->willReturn('Drupal\\example\\Entity\\Alpha');
    $content->method('getProvider')->willReturn('example');
    $content->method('getBaseTable')->willReturn('example_alpha');
    $content->method('getHandlerClasses')->willReturn([
      'storage' => 'Drupal\\example\\AlphaStorage',
      'form' => [
        'edit' => 'Drupal\\example\\Form\\AlphaForm',
      ],
    ]);

    $configuration = $this->createMock(ConfigEntityTypeInterface::class);
    $configuration->method('getLabel')->willReturn('Zeta configuration');
    $configuration->method('getClass')
      ->willReturn('Drupal\\example\\Entity\\Zeta');
    $configuration->method('getProvider')->willReturn('example');
    $configuration->method('getBaseTable')->willReturn(NULL);
    $configuration->method('getConfigPrefix')->willReturn('example.zeta');
    $configuration->method('getHandlerClasses')->willReturn([
      'list_builder' => 'Drupal\\example\\ZetaListBuilder',
    ]);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getDefinitions')->willReturn([
      'zeta' => $configuration,
      'alpha' => $content,
    ]);

    $source_path_resolver = $this->createMock(
      SourcePathResolverInterface::class,
    );
    $source_path_resolver->method('resolve')->willReturnMap([
      ['Drupal\\example\\Entity\\Alpha', 'modules/example/src/Entity/Alpha.php'],
      ['Drupal\\example\\Entity\\Zeta', 'modules/example/src/Entity/Zeta.php'],
    ]);

    $collector = new EntityTypeCollector(
      $entity_type_manager,
      $source_path_resolver,
    );

    $this->assertSame('entity_types', $collector->id());
    $this->assertEquals([
      new EntityTypeComponent(
        entityTypeId: 'alpha',
        label: 'Alpha content',
        entityKind: 'content',
        entityClass: 'Drupal\\example\\Entity\\Alpha',
        provider: 'example',
        baseTable: 'example_alpha',
        configPrefix: NULL,
        handlerClasses: [
          'form.edit' => 'Drupal\\example\\Form\\AlphaForm',
          'storage' => 'Drupal\\example\\AlphaStorage',
        ],
        sourcePath: 'modules/example/src/Entity/Alpha.php',
      ),
      new EntityTypeComponent(
        entityTypeId: 'zeta',
        label: 'Zeta configuration',
        entityKind: 'configuration',
        entityClass: 'Drupal\\example\\Entity\\Zeta',
        provider: 'example',
        baseTable: NULL,
        configPrefix: 'example.zeta',
        handlerClasses: [
          'list_builder' => 'Drupal\\example\\ZetaListBuilder',
        ],
        sourcePath: 'modules/example/src/Entity/Zeta.php',
      ),
    ], $collector->collect());
  }

}
