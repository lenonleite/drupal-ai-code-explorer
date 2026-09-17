<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Collector;

use Drupal\Core\DrupalKernelInterface;
use Drupal\drupal_developer_assistant\Collector\PluginManagerCollector;
use Drupal\drupal_developer_assistant\Collector\ServiceCollector;
use Drupal\drupal_developer_assistant\Model\PluginManagerComponent;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests plugin manager discovery without booting Drupal.
 */
#[Group('drupal_developer_assistant')]
final class PluginManagerCollectorTest extends UnitTestCase {

  /**
   * Tests that only concrete plugin manager definitions are collected.
   */
  public function testCollectsPluginManagers(): void {
    $kernel = $this->createMock(DrupalKernelInterface::class);
    $kernel->method('getCachedContainerDefinition')->willReturn([
      'services' => [
        'ordinary.service' => serialize([
          'class' => 'Drupal\\example\\OrdinaryService',
        ]),
        'plugin.manager.block' => serialize([
          'class' => 'Drupal\\Core\\Block\\BlockManager',
        ]),
        'plugin.manager.action' => serialize([
          'class' => 'Drupal\\Core\\Action\\ActionManager',
        ]),
      ],
      'aliases' => [
        'plugin.manager.block_alias' => 'plugin.manager.block',
      ],
    ]);

    $collector = new PluginManagerCollector(new ServiceCollector($kernel));

    $this->assertSame('plugin_managers', $collector->id());
    $this->assertEquals([
      new PluginManagerComponent(
        pluginType: 'action',
        managerServiceId: 'plugin.manager.action',
        className: 'Drupal\\Core\\Action\\ActionManager',
      ),
      new PluginManagerComponent(
        pluginType: 'block',
        managerServiceId: 'plugin.manager.block',
        className: 'Drupal\\Core\\Block\\BlockManager',
      ),
    ], $collector->collect());
  }

}
