<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Collector;

use Drupal\Core\Action\ActionManager;
use Drupal\Core\Action\Plugin\Action\GotoAction;
use Drupal\drupal_developer_assistant\Collector\ActionPluginCollector;
use Drupal\drupal_developer_assistant\Model\ActionPluginComponent;
use Drupal\drupal_developer_assistant\Resolver\ReflectionSourcePathResolver;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Action plugin definition normalization without booting Drupal.
 */
#[Group('drupal_developer_assistant')]
final class ActionPluginCollectorTest extends UnitTestCase {

  /**
   * Tests normalized and sorted Action plugin records.
   */
  public function testCollectsActionPlugins(): void {
    $action_manager = $this->createMock(ActionManager::class);
    $action_manager->method('getDefinitions')->willReturn([
      'zeta' => [
        'class' => 'Drupal\\example\\Plugin\\Action\\ZetaAction',
        'provider' => 'example',
        'label' => 'Zeta action',
        'type' => 'node',
      ],
      'alpha' => [
        'class' => GotoAction::class,
        'provider' => 'core',
        'label' => 'Alpha action',
        'type' => 'system',
      ],
    ]);

    $collector = new ActionPluginCollector(
      $action_manager,
      new ReflectionSourcePathResolver(DRUPAL_ROOT),
    );

    $this->assertSame('plugins.action', $collector->id());
    $this->assertEquals([
      new ActionPluginComponent(
        pluginId: 'alpha',
        label: 'Alpha action',
        className: GotoAction::class,
        provider: 'core',
        sourcePath: 'core/lib/Drupal/Core/Action/Plugin/Action/GotoAction.php',
        targetType: 'system',
      ),
      new ActionPluginComponent(
        pluginId: 'zeta',
        label: 'Zeta action',
        className: 'Drupal\\example\\Plugin\\Action\\ZetaAction',
        provider: 'example',
        sourcePath: NULL,
        targetType: 'node',
      ),
    ], $collector->collect());
  }

}
