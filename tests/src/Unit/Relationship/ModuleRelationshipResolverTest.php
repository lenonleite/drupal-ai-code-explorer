<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Relationship;

use Drupal\Core\Action\ActionManager;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\InfoParserInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\drupal_developer_assistant\Collector\ActionPluginCollector;
use Drupal\drupal_developer_assistant\Collector\BlockPluginCollector;
use Drupal\drupal_developer_assistant\Collector\ConfigurationCollector;
use Drupal\drupal_developer_assistant\Collector\EntityTypeCollector;
use Drupal\drupal_developer_assistant\Collector\HookCollector;
use Drupal\drupal_developer_assistant\Collector\ModuleCollector;
use Drupal\drupal_developer_assistant\Model\ComponentRelationship;
use Drupal\drupal_developer_assistant\Relationship\ModuleRelationshipResolver;
use Drupal\drupal_developer_assistant\Resolver\SourcePathResolverInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests module-related component relationships without booting Drupal.
 */
#[Group('drupal_developer_assistant')]
final class ModuleRelationshipResolverTest extends UnitTestCase {

  /**
   * Tests module dependencies and explicit component providers.
   */
  public function testResolvesModuleRelationships(): void {
    $source_path_resolver = $this->createMock(
      SourcePathResolverInterface::class,
    );
    $source_path_resolver->method('resolve')->willReturnMap([
      ['Drupal\\example\\Plugin\\Block\\ExampleBlock', 'block.php'],
      ['Drupal\\example\\Plugin\\Action\\ExampleAction', 'action.php'],
      ['Drupal\\example\\Entity\\ExampleItem', 'entity.php'],
    ]);
    $source_path_resolver->method('resolveFunction')->willReturnMap([
      ['example_cron', 'example.module'],
    ]);

    $extension = $this->createMock(Extension::class);
    $extension->method('getPathname')->willReturn('example.info.yml');
    $extension->method('getPath')->willReturn('modules/example');
    $module_handler = $this->createMock(ModuleHandlerInterface::class);
    $module_handler->method('getModuleList')->willReturn([
      'example' => $extension,
    ]);
    $info_parser = $this->createMock(InfoParserInterface::class);
    $info_parser->method('parse')->willReturn([
      'name' => 'Example',
      'dependencies' => ['drupal:system (>=11.0)'],
    ]);

    $block_manager = $this->createMock(BlockManagerInterface::class);
    $block_manager->method('getDefinitions')->willReturn([
      'example_block' => [
        'class' => 'Drupal\\example\\Plugin\\Block\\ExampleBlock',
        'provider' => 'example',
        'admin_label' => 'Example block',
      ],
    ]);

    $action_manager = $this->createMock(ActionManager::class);
    $action_manager->method('getDefinitions')->willReturn([
      'example_action' => [
        'class' => 'Drupal\\example\\Plugin\\Action\\ExampleAction',
        'provider' => 'example',
        'label' => 'Example action',
        'type' => 'node',
      ],
    ]);

    $hook_store = $this->createMock(KeyValueStoreInterface::class);
    $hook_store->method('get')->with('hook_list', [])->willReturn([
      'cron' => ['example_cron' => 'example'],
    ]);
    $key_value_factory = $this->createMock(KeyValueFactoryInterface::class);
    $key_value_factory->method('get')->with('hook_data')
      ->willReturn($hook_store);

    $configuration = $this->createMock(ImmutableConfig::class);
    $configuration->method('getRawData')->willReturn(['id' => 'alpha']);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('listAll')->willReturn(['example.item.alpha']);
    $config_factory->method('loadMultiple')->willReturn([
      'example.item.alpha' => $configuration,
    ]);
    $config_manager = $this->createMock(ConfigManagerInterface::class);
    $config_manager->method('getEntityTypeIdByName')
      ->willReturn('example_item');

    $entity_type = $this->createMock(ConfigEntityTypeInterface::class);
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

    $resolver = new ModuleRelationshipResolver(
      new ModuleCollector($module_handler, $info_parser),
      new BlockPluginCollector($block_manager, $source_path_resolver),
      new ActionPluginCollector($action_manager, $source_path_resolver),
      new HookCollector($key_value_factory, $source_path_resolver),
      new ConfigurationCollector(
        $config_factory,
        $config_manager,
        new EntityTypeCollector(
          $entity_type_manager,
          $source_path_resolver,
        ),
      ),
    );

    $this->assertSame('modules', $resolver->id());
    $this->assertEquals([
      new ComponentRelationship(
        sourceType: 'module',
        sourceId: 'example',
        relationship: 'depends_on',
        targetType: 'module',
        targetId: 'system',
        metadata: [
          'declaration' => 'drupal:system (>=11.0)',
          'project' => 'drupal',
          'constraint' => '>=11.0',
        ],
      ),
      new ComponentRelationship(
        sourceType: 'plugin',
        sourceId: 'block:example_block',
        relationship: 'provided_by',
        targetType: 'module',
        targetId: 'example',
        metadata: [
          'label' => 'Example block',
          'plugin_type' => 'block',
          'class' => 'Drupal\\example\\Plugin\\Block\\ExampleBlock',
          'source_path' => 'block.php',
        ],
      ),
      new ComponentRelationship(
        sourceType: 'plugin',
        sourceId: 'action:example_action',
        relationship: 'provided_by',
        targetType: 'module',
        targetId: 'example',
        metadata: [
          'label' => 'Example action',
          'plugin_type' => 'action',
          'class' => 'Drupal\\example\\Plugin\\Action\\ExampleAction',
          'source_path' => 'action.php',
        ],
      ),
      new ComponentRelationship(
        sourceType: 'hook_implementation',
        sourceId: 'cron:example_cron',
        relationship: 'provided_by',
        targetType: 'module',
        targetId: 'example',
        metadata: [
          'callable' => 'example_cron',
          'hook' => 'cron',
          'style' => 'procedural',
          'source_path' => 'example.module',
        ],
      ),
      new ComponentRelationship(
        sourceType: 'configuration',
        sourceId: 'example.item.alpha',
        relationship: 'provided_by',
        targetType: 'module',
        targetId: 'example',
        metadata: [
          'configuration_type' => 'configuration_entity',
          'entity_type' => 'example_item',
        ],
      ),
    ], $resolver->resolve());
  }

}
