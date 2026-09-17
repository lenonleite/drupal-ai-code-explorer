<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Relationship;

use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\drupal_developer_assistant\Collector\EntityTypeCollector;
use Drupal\drupal_developer_assistant\Collector\RouteCollector;
use Drupal\drupal_developer_assistant\Collector\ServiceCollector;
use Drupal\drupal_developer_assistant\Model\ComponentRelationship;
use Drupal\drupal_developer_assistant\Relationship\RouteRelationshipResolver;
use Drupal\drupal_developer_assistant\Resolver\SourcePathResolverInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;

/**
 * Tests route-backed component relationships without booting Drupal.
 */
#[Group('drupal_developer_assistant')]
final class RouteRelationshipResolverTest extends UnitTestCase {

  /**
   * Tests controller, form, entity-form, and permission relationships.
   */
  public function testResolvesRouteRelationships(): void {
    $route_provider = $this->createMock(RouteProviderInterface::class);
    $route_provider->method('getAllRoutes')->willReturn([
      'gamma.entity_form' => new Route(
        '/example/{example}/edit',
        ['_entity_form' => 'example.edit'],
        ['_permission' => 'edit examples'],
      ),
      'alpha.controller' => new Route(
        '/alpha',
        ['_controller' => '\\Drupal\\example\\Controller\\ExampleController::build'],
        ['_permission' => 'access examples'],
      ),
      'delta.entity_list' => new Route(
        '/examples',
        ['_entity_list' => 'example'],
        ['_permission' => 'administer examples'],
      ),
      'beta.form' => new Route(
        '/settings',
        ['_form' => '\\Drupal\\example\\Form\\SettingsForm'],
      ),
    ]);

    $kernel = $this->createMock(DrupalKernelInterface::class);
    $kernel->method('getCachedContainerDefinition')->willReturn([]);

    $entity_type = $this->createMock(ContentEntityTypeInterface::class);
    $entity_type->method('getLabel')->willReturn('Example');
    $entity_type->method('getClass')
      ->willReturn('Drupal\\example\\Entity\\Example');
    $entity_type->method('getProvider')->willReturn('example');
    $entity_type->method('getBaseTable')->willReturn('example');
    $entity_type->method('getHandlerClasses')->willReturn([
      'form' => [
        'edit' => 'Drupal\\example\\Form\\ExampleEditForm',
      ],
      'list_builder' => 'Drupal\\example\\ExampleListBuilder',
    ]);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getDefinitions')->willReturn([
      'example' => $entity_type,
    ]);

    $source_path_resolver = $this->createMock(
      SourcePathResolverInterface::class,
    );
    $source_path_resolver->method('resolve')->willReturn(NULL);

    $route_collector = new RouteCollector(
      $route_provider,
      new ServiceCollector($kernel),
      new EntityTypeCollector($entity_type_manager, $source_path_resolver),
      $source_path_resolver,
    );
    $resolver = new RouteRelationshipResolver($route_collector);

    $this->assertSame('routes', $resolver->id());
    $this->assertEquals([
      new ComponentRelationship(
        sourceType: 'route',
        sourceId: 'alpha.controller',
        relationship: 'invokes',
        targetType: 'controller',
        targetId: 'Drupal\\example\\Controller\\ExampleController',
        metadata: [
          'method' => 'build',
          'path' => '/alpha',
          'raw_target' => '\\Drupal\\example\\Controller\\ExampleController::build',
        ],
      ),
      new ComponentRelationship(
        sourceType: 'route',
        sourceId: 'alpha.controller',
        relationship: 'requires',
        targetType: 'permission',
        targetId: 'access examples',
        metadata: ['path' => '/alpha'],
      ),
      new ComponentRelationship(
        sourceType: 'route',
        sourceId: 'beta.form',
        relationship: 'builds',
        targetType: 'form',
        targetId: 'Drupal\\example\\Form\\SettingsForm',
        metadata: [
          'path' => '/settings',
          'raw_target' => '\\Drupal\\example\\Form\\SettingsForm',
          'route_target_type' => 'form',
        ],
      ),
      new ComponentRelationship(
        sourceType: 'route',
        sourceId: 'delta.entity_list',
        relationship: 'requires',
        targetType: 'permission',
        targetId: 'administer examples',
        metadata: ['path' => '/examples'],
      ),
      new ComponentRelationship(
        sourceType: 'route',
        sourceId: 'gamma.entity_form',
        relationship: 'builds',
        targetType: 'form',
        targetId: 'Drupal\\example\\Form\\ExampleEditForm',
        metadata: [
          'path' => '/example/{example}/edit',
          'raw_target' => 'example.edit',
          'route_target_type' => 'entity_form',
        ],
      ),
      new ComponentRelationship(
        sourceType: 'route',
        sourceId: 'gamma.entity_form',
        relationship: 'requires',
        targetType: 'permission',
        targetId: 'edit examples',
        metadata: ['path' => '/example/{example}/edit'],
      ),
    ], $resolver->resolve());
  }

}
