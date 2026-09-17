<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Collector;

use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\drupal_developer_assistant\Collector\ControllerCollector;
use Drupal\drupal_developer_assistant\Collector\EntityTypeCollector;
use Drupal\drupal_developer_assistant\Collector\RouteCollector;
use Drupal\drupal_developer_assistant\Collector\ServiceCollector;
use Drupal\drupal_developer_assistant\Model\ControllerComponent;
use Drupal\drupal_developer_assistant\Resolver\SourcePathResolverInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;

/**
 * Tests controller aggregation without booting Drupal.
 */
#[Group('drupal_developer_assistant')]
final class ControllerCollectorTest extends UnitTestCase {

  /**
   * Tests grouping and callback normalization for controller routes.
   */
  public function testCollectsUniqueControllersFromRoutes(): void {
    $route_provider = $this->createMock(RouteProviderInterface::class);
    $route_provider->method('getAllRoutes')->willReturn([
      'zeta.direct' => new Route(
        '/zeta',
        ['_controller' => '\\Drupal\\example\\Controller\\DirectController::second'],
      ),
      'alpha.direct' => new Route(
        '/alpha',
        ['_controller' => '\\Drupal\\example\\Controller\\DirectController::first'],
      ),
      'beta.service' => new Route(
        '/beta',
        ['_controller' => 'example.controller:build'],
      ),
      'delta.invokable' => new Route(
        '/delta',
        ['_controller' => 'Drupal\\example\\Controller\\InvokableController'],
      ),
      'epsilon.form' => new Route(
        '/settings',
        ['_form' => 'Drupal\\example\\Form\\SettingsForm'],
      ),
      'gamma.legacy' => new Route(
        '/gamma',
        ['_controller' => '\\Drupal\\legacy\\Controller\\LegacyController:show'],
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

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getDefinitions')->willReturn([]);

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
    $collector = new ControllerCollector($route_collector);

    $controllers = $collector->collect();

    $this->assertSame('controllers', $collector->id());
    $this->assertContainsOnlyInstancesOf(
      ControllerComponent::class,
      $controllers,
    );
    $this->assertSame([
      'Drupal\\example\\Controller\\DirectController',
      'Drupal\\example\\Controller\\InvokableController',
      'Drupal\\example\\Controller\\ServiceController',
      'Drupal\\legacy\\Controller\\LegacyController',
    ], array_column($controllers, 'className'));

    $this->assertSame('DirectController', $controllers[0]->label);
    $this->assertSame([
      'alpha.direct' => '/alpha',
      'zeta.direct' => '/zeta',
    ], $controllers[0]->routePaths);
    $this->assertSame([
      'alpha.direct' => 'first',
      'zeta.direct' => 'second',
    ], $controllers[0]->routeCallbacks);
    $this->assertSame([], $controllers[0]->serviceIds);
    $this->assertSame('__invoke', $controllers[1]->routeCallbacks['delta.invokable']);
    $this->assertSame(['example.controller'], $controllers[2]->serviceIds);
    $this->assertSame('show', $controllers[3]->routeCallbacks['gamma.legacy']);
    $this->assertSame(
      'source/Drupal/legacy/Controller/LegacyController.php',
      $controllers[3]->sourcePath,
    );
  }

}
