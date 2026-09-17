<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Collector;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\drupal_developer_assistant\Collector\NavigationCollector;
use Drupal\drupal_developer_assistant\Model\NavigationComponent;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal navigation YAML collection without booting Drupal.
 */
#[Group('drupal_developer_assistant')]
final class NavigationCollectorTest extends UnitTestCase {

  /**
   * Temporary application root used by each test.
   */
  private string $testRoot;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->testRoot = sys_get_temp_dir()
      . '/drupal-developer-assistant-navigation-'
      . bin2hex(random_bytes(6));
    mkdir($this->testRoot . '/modules/custom/example', 0777, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $files = glob($this->testRoot . '/modules/custom/example/*');
    foreach ($files ?: [] as $file) {
      unlink($file);
    }
    rmdir($this->testRoot . '/modules/custom/example');
    rmdir($this->testRoot . '/modules/custom');
    rmdir($this->testRoot . '/modules');
    rmdir($this->testRoot);
    parent::tearDown();
  }

  /**
   * Tests menu, task, and action definition normalization.
   */
  public function testCollectsNavigationDefinitions(): void {
    $directory = $this->testRoot . '/modules/custom/example';
    file_put_contents($directory . '/example.links.menu.yml', <<<'YAML'
example.settings:
  title: Settings
  route_name: example.settings
  parent: system.admin_config
  menu_name: admin
YAML);
    file_put_contents($directory . '/example.links.task.yml', <<<'YAML'
example.overview:
  title: Overview
  route_name: example.overview
  base_route: example.overview
example.settings:
  title: Settings
  route_name: example.settings
  parent_id: example.overview
YAML);
    file_put_contents($directory . '/example.links.action.yml', <<<'YAML'
example.add:
  title: Add example
  route_name: example.add
  appears_on:
    - example.overview
YAML);

    $module_handler = $this->createMock(ModuleHandlerInterface::class);
    $module_handler->method('getModuleDirectories')->willReturn([
      'example' => $directory,
    ]);
    $collector = new NavigationCollector($module_handler, $this->testRoot);
    $records = $collector->collect();

    $this->assertSame('navigation', $collector->id());
    $this->assertCount(4, $records);
    $this->assertContainsOnlyInstancesOf(NavigationComponent::class, $records);
    $this->assertEquals(new NavigationComponent(
      definitionId: 'example.settings',
      navigationType: 'menu_link',
      label: 'Settings',
      provider: 'example',
      routeName: 'example.settings',
      parentId: 'system.admin_config',
      baseRoute: NULL,
      appearsOn: [],
      menuName: 'admin',
      sourcePath: 'modules/custom/example/example.links.menu.yml',
    ), $records[0]);
    $this->assertSame('local_task', $records[1]->type);
    $this->assertSame('example.overview', $records[1]->baseRoute);
    $this->assertSame('example.overview', $records[2]->parentId);
    $this->assertSame('local_action', $records[3]->type);
    $this->assertSame(['example.overview'], $records[3]->appearsOn);
  }

}
