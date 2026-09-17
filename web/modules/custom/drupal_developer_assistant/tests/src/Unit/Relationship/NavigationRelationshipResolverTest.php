<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Relationship;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\drupal_developer_assistant\Collector\NavigationCollector;
use Drupal\drupal_developer_assistant\Model\ComponentRelationship;
use Drupal\drupal_developer_assistant\Relationship\NavigationRelationshipResolver;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests deterministic navigation relationships without booting Drupal.
 */
#[Group('drupal_developer_assistant')]
final class NavigationRelationshipResolverTest extends UnitTestCase {

  /**
   * Tests raw definition edges and derived route transitions.
   */
  public function testResolvesNavigationRelationships(): void {
    $root = sys_get_temp_dir()
      . '/drupal-developer-assistant-navigation-rel-'
      . bin2hex(random_bytes(6));
    $directory = $root . '/modules/custom/example';
    mkdir($directory, 0777, TRUE);
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
  title: Add
  route_name: example.add
  appears_on:
    - example.overview
YAML);

    try {
      $module_handler = $this->createMock(ModuleHandlerInterface::class);
      $module_handler->method('getModuleDirectories')->willReturn([
        'example' => $directory,
      ]);
      $resolver = new NavigationRelationshipResolver(
        new NavigationCollector($module_handler, $root),
      );
      $relationships = $resolver->resolve();

      $this->assertSame('navigation', $resolver->id());
      $this->assertContainsEquals(new ComponentRelationship(
        sourceType: 'local_task',
        sourceId: 'example.settings',
        relationship: 'child_of',
        targetType: 'local_task',
        targetId: 'example.overview',
        metadata: [
          'navigation_type' => 'local_task',
          'source_path' => 'modules/custom/example/example.links.task.yml',
        ],
      ), $relationships);
      $this->assertContainsEquals(new ComponentRelationship(
        sourceType: 'route',
        sourceId: 'example.overview',
        relationship: 'navigates_to',
        targetType: 'route',
        targetId: 'example.settings',
        metadata: [
          'navigation_type' => 'local_task',
          'source_path' => 'modules/custom/example/example.links.task.yml',
          'navigation_id' => 'example.settings',
          'via' => 'parent',
        ],
      ), $relationships);
      $this->assertContainsEquals(new ComponentRelationship(
        sourceType: 'route',
        sourceId: 'example.overview',
        relationship: 'navigates_to',
        targetType: 'route',
        targetId: 'example.add',
        metadata: [
          'navigation_type' => 'local_action',
          'source_path' => 'modules/custom/example/example.links.action.yml',
          'navigation_id' => 'example.add',
          'via' => 'appears_on',
        ],
      ), $relationships);
      $this->assertNotContainsEquals(new ComponentRelationship(
        'route',
        'example.overview',
        'navigates_to',
        'route',
        'example.overview',
      ), $relationships);
    }
    finally {
      foreach (glob($directory . '/*') ?: [] as $file) {
        unlink($file);
      }
      rmdir($directory);
      rmdir($root . '/modules/custom');
      rmdir($root . '/modules');
      rmdir($root);
    }
  }

}
