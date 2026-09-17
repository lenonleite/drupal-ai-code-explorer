<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Collector;

use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\drupal_developer_assistant\Collector\EntityTypeCollector;
use Drupal\drupal_developer_assistant\Collector\FormCollector;
use Drupal\drupal_developer_assistant\Collector\RouteCollector;
use Drupal\drupal_developer_assistant\Collector\ServiceCollector;
use Drupal\drupal_developer_assistant\Model\FormComponent;
use Drupal\drupal_developer_assistant\Resolver\SourcePathResolverInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;

/**
 * Tests form aggregation without booting Drupal.
 */
#[Group('drupal_developer_assistant')]
final class FormCollectorTest extends UnitTestCase {

  /**
   * Tests regular-form and entity-form route aggregation.
   */
  public function testCollectsUniqueFormsFromRoutes(): void {
    $route_provider = $this->createMock(RouteProviderInterface::class);
    $route_provider->method('getAllRoutes')->willReturn([
      'zeta.regular' => new Route(
        '/zeta',
        ['_form' => '\\Drupal\\example\\Form\\AlphaForm'],
      ),
      'alpha.regular' => new Route(
        '/alpha',
        ['_form' => '\\Drupal\\example\\Form\\AlphaForm'],
      ),
      'beta.entity_edit' => new Route(
        '/example/{example}/edit',
        ['_entity_form' => 'example.edit'],
      ),
      'gamma.entity_delete' => new Route(
        '/example/{example}/delete',
        ['_entity_form' => 'example.delete'],
      ),
      'delta.controller' => new Route(
        '/delta',
        ['_controller' => 'Drupal\\example\\Controller\\ExampleController'],
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
        'delete' => 'Drupal\\example\\Form\\DeleteForm',
        'edit' => 'Drupal\\example\\Form\\AlphaForm',
      ],
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

    $route_collector = new RouteCollector(
      $route_provider,
      new ServiceCollector($kernel),
      new EntityTypeCollector($entity_type_manager, $source_path_resolver),
      $source_path_resolver,
    );
    $collector = new FormCollector($route_collector);

    $forms = $collector->collect();

    $this->assertSame('forms', $collector->id());
    $this->assertContainsOnlyInstancesOf(FormComponent::class, $forms);
    $this->assertSame([
      'Drupal\\example\\Form\\AlphaForm',
      'Drupal\\example\\Form\\DeleteForm',
    ], array_column($forms, 'className'));

    $this->assertSame('AlphaForm', $forms[0]->label);
    $this->assertSame([
      'alpha.regular' => '/alpha',
      'beta.entity_edit' => '/example/{example}/edit',
      'zeta.regular' => '/zeta',
    ], $forms[0]->routePaths);
    $this->assertSame([
      'alpha.regular' => 'form',
      'beta.entity_edit' => 'entity_form',
      'zeta.regular' => 'form',
    ], $forms[0]->routeTypes);
    $this->assertSame([
      'alpha.regular' => '\\Drupal\\example\\Form\\AlphaForm',
      'beta.entity_edit' => 'example.edit',
      'zeta.regular' => '\\Drupal\\example\\Form\\AlphaForm',
    ], $forms[0]->routeTargets);
    $this->assertSame(
      'source/Drupal/example/Form/DeleteForm.php',
      $forms[1]->sourcePath,
    );
  }

}
