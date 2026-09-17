<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Collector;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface as EntityDefinitionInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\drupal_developer_assistant\Collector\ConfigurationCollector;
use Drupal\drupal_developer_assistant\Collector\EntityTypeCollector;
use Drupal\drupal_developer_assistant\Model\ConfigurationComponent;
use Drupal\drupal_developer_assistant\Resolver\SourcePathResolverInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests active-configuration metadata collection without booting Drupal.
 */
#[Group('drupal_developer_assistant')]
final class ConfigurationCollectorTest extends UnitTestCase {

  /**
   * Tests simple configuration, config entities, keys, and dependencies.
   */
  public function testCollectsConfigurationMetadataWithoutValues(): void {
    $entity_configuration = $this->createMock(ImmutableConfig::class);
    $entity_configuration->method('getRawData')->willReturn([
      'label' => 'Private label value',
      'dependencies' => [
        'module' => ['zeta', 'alpha'],
        'config' => ['example.settings'],
        'enforced' => [
          'module' => ['forced_module'],
          'theme' => ['forced_theme'],
        ],
        'invalid' => [42],
      ],
      'id' => 'alpha',
    ]);

    $simple_configuration = $this->createMock(ImmutableConfig::class);
    $simple_configuration->method('getRawData')->willReturn([
      'api_secret' => 'secret-value-must-not-be-collected',
      'enabled' => TRUE,
    ]);

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('listAll')->willReturn([
      'zeta.settings',
      'example.item.alpha',
    ]);
    $config_factory->method('loadMultiple')->with([
      'example.item.alpha',
      'zeta.settings',
    ])->willReturn([
      'example.item.alpha' => $entity_configuration,
      'zeta.settings' => $simple_configuration,
    ]);

    $config_manager = $this->createMock(ConfigManagerInterface::class);
    $config_manager->method('getEntityTypeIdByName')->willReturnMap([
      ['example.item.alpha', 'example_item'],
      ['zeta.settings', NULL],
    ]);

    $entity_type = $this->createMock(EntityDefinitionInterface::class);
    $entity_type->method('getLabel')->willReturn('Example item');
    $entity_type->method('getClass')
      ->willReturn('Drupal\\example\\Entity\\ExampleItem');
    $entity_type->method('getProvider')->willReturn('example');
    $entity_type->method('getBaseTable')->willReturn(NULL);
    $entity_type->method('getConfigPrefix')->willReturn('example.item');
    $entity_type->method('getHandlerClasses')->willReturn([]);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getDefinitions')->willReturn([
      'example_item' => $entity_type,
    ]);

    $source_path_resolver = $this->createMock(
      SourcePathResolverInterface::class,
    );
    $source_path_resolver->method('resolve')->willReturnMap([
      [
        'Drupal\\example\\Entity\\ExampleItem',
        'modules/example/src/Entity/ExampleItem.php',
      ],
    ]);

    $collector = new ConfigurationCollector(
      $config_factory,
      $config_manager,
      new EntityTypeCollector($entity_type_manager, $source_path_resolver),
    );

    $this->assertSame('configuration', $collector->id());
    $this->assertEquals([
      new ConfigurationComponent(
        configurationName: 'example.item.alpha',
        configurationType: 'configuration_entity',
        entityTypeId: 'example_item',
        provider: 'example',
        entityTypeClass: 'Drupal\\example\\Entity\\ExampleItem',
        topLevelKeys: ['dependencies', 'id', 'label'],
        dependencies: [
          'config' => ['example.settings'],
          'enforced.module' => ['forced_module'],
          'enforced.theme' => ['forced_theme'],
          'module' => ['alpha', 'zeta'],
        ],
        entityTypeSourcePath: 'modules/example/src/Entity/ExampleItem.php',
      ),
      new ConfigurationComponent(
        configurationName: 'zeta.settings',
        configurationType: 'simple',
        entityTypeId: NULL,
        provider: NULL,
        entityTypeClass: NULL,
        topLevelKeys: ['api_secret', 'enabled'],
        dependencies: [],
        entityTypeSourcePath: NULL,
      ),
    ], $collector->collect());
    $this->assertStringNotContainsString(
      'secret-value-must-not-be-collected',
      serialize($collector->collect()),
    );
  }

}
