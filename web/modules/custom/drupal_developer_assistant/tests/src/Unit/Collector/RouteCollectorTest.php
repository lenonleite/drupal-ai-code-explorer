<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Collector;

use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\drupal_developer_assistant\Collector\EntityTypeCollector;
use Drupal\drupal_developer_assistant\Collector\RouteCollector;
use Drupal\drupal_developer_assistant\Collector\ServiceCollector;
use Drupal\drupal_developer_assistant\Model\RouteComponent;
use Drupal\drupal_developer_assistant\Resolver\SourcePathResolverInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;

/**
 * Tests route normalization and execution-target resolution.
 */
#[Group('drupal_developer_assistant')]
final class RouteCollectorTest extends UnitTestCase {

  /**
   * Tests class, service, form, and entity route targets.
   */
  public function testCollectsRoutesAndResolvesTargetClasses(): void {
    $route_provider = $this->createMock(RouteProviderInterface::class);
    $route_provider->method('getAllRoutes')->willReturn([
      'zeta.entity_view' => new Route(
        '/example/{example}',
        ['_entity_view' => 'example.full'],
      ),
      'alpha.controller' => new Route(
        '/alpha',
        ['_controller' => '\\Drupal\\example\\Controller\\DirectController::build'],
        ['_permission' => 'access content'],
        ['_admin_route' => TRUE],
        methods: ['GET'],
      ),
      'beta.service_controller' => new Route(
        '/beta',
        ['_controller' => 'example.controller:build'],
      ),
      'delta.entity_form' => new Route(
        '/example/{example}/edit',
        ['_entity_form' => 'example.edit'],
      ),
      'epsilon.entity_list' => new Route(
        '/examples',
        ['_entity_list' => 'example'],
      ),
      'gamma.form' => new Route(
        '/settings',
        ['_form' => '\\Drupal\\example\\Form\\SettingsForm'],
        methods: ['GET', 'POST'],
      ),
    ]);

    $kernel = $this->createMock(DrupalKernelInterface::class);
    $kernel->method('getCachedContainerDefinition')->willReturn([
      'services' => [
        'example.controller' => serialize([
          'class' => 'Drupal\\example\\Controller\\ServiceController',
        ]),
      ],
    ]);

    $entity_type = $this->createMock(ContentEntityTypeInterface::class);
    $entity_type->method('getLabel')->willReturn('Example');
    $entity_type->method('getClass')
      ->willReturn('Drupal\\example\\Entity\\Example');
    $entity_type->method('getProvider')->willReturn('example');
    $entity_type->method('getBaseTable')->willReturn('example');
    $entity_type->method('getHandlerClasses')->willReturn([
      'form' => [
        'default' => 'Drupal\\example\\Form\\ExampleDefaultForm',
        'edit' => 'Drupal\\example\\Form\\ExampleEditForm',
      ],
      'list_builder' => 'Drupal\\example\\ExampleListBuilder',
      'view_builder' => 'Drupal\\example\\ExampleViewBuilder',
    ]);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getDefinitions')->willReturn([
      'example' => $entity_type,
    ]);

    $source_path_resolver = $this->createMock(
      SourcePathResolverInterface::class,
    );
    $source_path_resolver->method('resolve')->willReturnCallback(
      static fn (?string $class): ?string => $class === NULL
        ? NULL
        : 'source/' . str_replace('\\', '/', $class) . '.php',
    );

    $collector = new RouteCollector(
      $route_provider,
      new ServiceCollector($kernel),
      new EntityTypeCollector($entity_type_manager, $source_path_resolver),
      $source_path_resolver,
    );

    $routes = $collector->collect();

    $this->assertSame('routes', $collector->id());
    $this->assertContainsOnlyInstancesOf(RouteComponent::class, $routes);
    $this->assertSame([
      'alpha.controller',
      'beta.service_controller',
      'delta.entity_form',
      'epsilon.entity_list',
      'gamma.form',
      'zeta.entity_view',
    ], array_column($routes, 'id'));

    $this->assertSame('controller', $routes[0]->targetType);
    $this->assertSame(
      'Drupal\\example\\Controller\\DirectController',
      $routes[0]->targetClass,
    );
    $this->assertSame(['GET'], $routes[0]->methods);
    $this->assertSame(
      ['_permission' => 'access content'],
      $routes[0]->requirements,
    );
    $this->assertTrue($routes[0]->adminRoute);
    $this->assertSame(
      'source/Drupal/example/Controller/DirectController.php',
      $routes[0]->sourcePath,
    );

    $this->assertSame(
      'Drupal\\example\\Controller\\ServiceController',
      $routes[1]->targetClass,
    );
    $this->assertSame(
      'Drupal\\example\\Form\\ExampleEditForm',
      $routes[2]->targetClass,
    );
    $this->assertSame(
      'Drupal\\example\\ExampleListBuilder',
      $routes[3]->targetClass,
    );
    $this->assertSame('form', $routes[4]->targetType);
    $this->assertSame(
      'Drupal\\example\\Form\\SettingsForm',
      $routes[4]->targetClass,
    );
    $this->assertSame(['GET', 'POST'], $routes[4]->methods);
    $this->assertSame(
      'Drupal\\example\\ExampleViewBuilder',
      $routes[5]->targetClass,
    );
  }

}
