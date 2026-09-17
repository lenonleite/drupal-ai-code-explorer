<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Architecture;

use Drupal\drupal_developer_assistant\Analyzer\PhpSourceAnalyzerInterface;
use Drupal\drupal_developer_assistant\Architecture\ModuleArchitectureCacheInterface;
use Drupal\drupal_developer_assistant\Architecture\ModuleArchitectureBuilder;
use Drupal\drupal_developer_assistant\Collector\CollectorInterface;
use Drupal\drupal_developer_assistant\Collector\CollectorManagerInterface;
use Drupal\drupal_developer_assistant\Collector\ModuleSourceFileCollectorInterface;
use Drupal\drupal_developer_assistant\Model\ComponentRelationship;
use Drupal\drupal_developer_assistant\Model\HookComponent;
use Drupal\drupal_developer_assistant\Model\ModuleArchitecture;
use Drupal\drupal_developer_assistant\Model\ModuleArchitectureComponent;
use Drupal\drupal_developer_assistant\Model\ModuleComponent;
use Drupal\drupal_developer_assistant\Model\ModuleSourceFileComponent;
use Drupal\drupal_developer_assistant\Model\PluginComponent;
use Drupal\drupal_developer_assistant\Model\PhpFileAnalysis;
use Drupal\drupal_developer_assistant\Model\RouteComponent;
use Drupal\drupal_developer_assistant\Model\ServiceComponent;
use Drupal\drupal_developer_assistant\Relationship\PhpDependencyResolverInterface;
use Drupal\drupal_developer_assistant\Relationship\RelationshipManagerInterface;
use Drupal\drupal_developer_assistant\Resolver\SourcePathResolverInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests module-scoped architecture report building without booting Drupal.
 */
#[Group('drupal_developer_assistant')]
final class ModuleArchitectureBuilderTest extends UnitTestCase {

  /**
   * Tests ownership detection and outgoing relationship selection.
   */
  public function testBuildsModuleArchitecture(): void {
    $example_module = new ModuleComponent(
      id: 'example',
      label: 'Example',
      sourcePath: 'modules/custom/example',
      package: 'Custom',
      version: '1.0.0',
      dependencies: ['drupal:system'],
    );
    $module_collector = $this->createMock(CollectorInterface::class);
    $module_collector->method('id')->willReturn('modules');
    $module_collector->method('collect')->willReturn([
      $example_module,
      new ModuleComponent(
        id: 'other',
        label: 'Other',
        sourcePath: 'modules/custom/other',
        package: NULL,
        version: NULL,
        dependencies: [],
      ),
    ]);

    $service_component = new ServiceComponent(
      id: 'custom_service_id',
      className: 'Drupal\\example\\ExampleService',
      aliasTarget: NULL,
      references: ['constructor' => ['logger.channel.default']],
    );
    $component_collector = $this->createMock(CollectorInterface::class);
    $component_collector->method('id')->willReturn('test_components');
    $component_collector->method('collect')->willReturn([
      new RouteComponent(
        routeName: 'example.overview',
        path: '/example',
        methods: [],
        targetType: 'controller',
        target: 'Drupal\\example\\ExampleController::build',
        targetClass: 'Drupal\\example\\ExampleController',
        requirements: [],
        adminRoute: FALSE,
        sourcePath: 'modules/custom/example/src/ExampleController.php',
      ),
      new RouteComponent(
        routeName: 'other.overview',
        path: '/other',
        methods: [],
        targetType: NULL,
        target: NULL,
        targetClass: NULL,
        requirements: [],
        adminRoute: FALSE,
        sourcePath: 'modules/custom/other/src/OtherController.php',
      ),
      $service_component,
      new PluginComponent(
        pluginType: 'block',
        pluginId: 'example_block',
        label: 'Example block',
        className: 'Drupal\\example\\Plugin\\Block\\ExampleBlock',
        provider: 'example',
        sourcePath: NULL,
      ),
      new HookComponent(
        hookName: 'cron',
        provider: 'example',
        implementationType: 'procedural',
        callable: 'example_cron',
        className: NULL,
        methodName: NULL,
        executionOrder: 1,
        sourcePath: 'modules/custom/example/example.module',
      ),
    ]);

    $collector_manager = $this->createMock(
      CollectorManagerInterface::class,
    );
    $collector_manager->method('get')->with('modules')
      ->willReturn($module_collector);
    $collector_manager->expects($this->once())->method('all')->willReturn([
      'modules' => $module_collector,
      'test_components' => $component_collector,
    ]);

    $relationships = [
      new ComponentRelationship(
        sourceType: 'module',
        sourceId: 'example',
        relationship: 'depends_on',
        targetType: 'module',
        targetId: 'system',
      ),
      new ComponentRelationship(
        sourceType: 'route',
        sourceId: 'example.overview',
        relationship: 'invokes',
        targetType: 'controller',
        targetId: 'Drupal\\example\\ExampleController',
      ),
      new ComponentRelationship(
        sourceType: 'service',
        sourceId: 'custom_service_id',
        relationship: 'depends_on',
        targetType: 'service',
        targetId: 'logger.channel.default',
      ),
      new ComponentRelationship(
        sourceType: 'plugin',
        sourceId: 'block:example_block',
        relationship: 'provided_by',
        targetType: 'module',
        targetId: 'example',
      ),
      new ComponentRelationship(
        sourceType: 'hook_implementation',
        sourceId: 'cron:example_cron',
        relationship: 'provided_by',
        targetType: 'module',
        targetId: 'example',
      ),
      new ComponentRelationship(
        sourceType: 'route',
        sourceId: 'other.overview',
        relationship: 'requires',
        targetType: 'permission',
        targetId: 'access content',
      ),
    ];
    $relationship_manager = $this->createMock(
      RelationshipManagerInterface::class,
    );
    $relationship_manager->method('resolveAll')->willReturn($relationships);

    $source_path_resolver = $this->createMock(
      SourcePathResolverInterface::class,
    );
    $source_path_resolver->method('resolve')->willReturnMap([
      [
        'Drupal\\example\\ExampleService',
        'modules/custom/example/src/ExampleService.php',
      ],
    ]);
    $source_files = [
      new ModuleSourceFileComponent(
        moduleId: 'example',
        relativePath: 'src/ExampleService.php',
        fileType: 'PHP',
        category: 'Source code',
        size: 100,
        sourcePath: 'modules/custom/example/src/ExampleService.php',
      ),
    ];
    $source_file_collector = $this->createMock(
      ModuleSourceFileCollectorInterface::class,
    );
    $source_file_collector->method('collect')->with($example_module)
      ->willReturn($source_files);
    $php_file_analysis = new PhpFileAnalysis(
      sourceFile: $source_files[0],
      namespaces: ['Drupal\\example'],
      imports: [],
      symbols: [],
      functions: [],
      error: NULL,
    );
    $php_source_analyzer = $this->createMock(
      PhpSourceAnalyzerInterface::class,
    );
    $php_source_analyzer->method('analyze')->with(
      $example_module,
      $source_files[0],
    )->willReturn($php_file_analysis);
    $php_dependency = new ComponentRelationship(
      sourceType: 'class',
      sourceId: 'Drupal\\example\\ExampleService',
      relationship: 'depends_on',
      targetType: 'service',
      targetId: 'logger.channel.default',
      metadata: [
        'context' => 'constructor',
        'parameter' => '$logger',
      ],
    );
    $php_dependency_resolver = $this->createMock(
      PhpDependencyResolverInterface::class,
    );
    $php_dependency_resolver->expects($this->once())
      ->method('resolve')
      ->with([$php_file_analysis], [$service_component])
      ->willReturn([$php_dependency]);
    $cached_architecture = NULL;
    $architecture_cache = $this->createMock(
      ModuleArchitectureCacheInterface::class,
    );
    $architecture_cache->method('get')
      ->willReturnCallback(
        static function (
          string $module_id,
        ) use (&$cached_architecture): ?ModuleArchitecture {
          return $module_id === 'example' ? $cached_architecture : NULL;
        },
      );
    $architecture_cache->expects($this->once())
      ->method('set')
      ->with('example', $this->isInstanceOf(ModuleArchitecture::class))
      ->willReturnCallback(
        static function (
          string $module_id,
          ModuleArchitecture $architecture,
        ) use (&$cached_architecture): void {
          $cached_architecture = $architecture;
        },
      );

    $builder = new ModuleArchitectureBuilder(
      $collector_manager,
      $relationship_manager,
      $source_path_resolver,
      $source_file_collector,
      $php_source_analyzer,
      $php_dependency_resolver,
      $architecture_cache,
    );
    $architecture = $builder->build('example');

    $this->assertNotNull($architecture);
    $this->assertSame($example_module, $architecture->module);
    $this->assertEquals([
      new ModuleArchitectureComponent(
        type: 'hook_implementation',
        id: 'cron:example_cron',
        label: 'hook_cron',
        sourcePath: 'modules/custom/example/example.module',
      ),
      new ModuleArchitectureComponent(
        type: 'plugin',
        id: 'block:example_block',
        label: 'Example block',
        sourcePath: NULL,
      ),
      new ModuleArchitectureComponent(
        type: 'route',
        id: 'example.overview',
        label: '/example',
        sourcePath: 'modules/custom/example/src/ExampleController.php',
      ),
      new ModuleArchitectureComponent(
        type: 'service',
        id: 'custom_service_id',
        label: 'Drupal\\example\\ExampleService',
        sourcePath: 'modules/custom/example/src/ExampleService.php',
      ),
    ], $architecture->components);
    $this->assertSame([
      ...array_slice($relationships, 0, 5),
      $php_dependency,
    ], $architecture->relationships);
    $this->assertSame($source_files, $architecture->sourceFiles);
    $this->assertSame([$php_file_analysis], $architecture->phpFiles);
    $this->assertSame($architecture, $builder->build('example'));
    $this->assertNull($builder->build('missing'));
  }

}
