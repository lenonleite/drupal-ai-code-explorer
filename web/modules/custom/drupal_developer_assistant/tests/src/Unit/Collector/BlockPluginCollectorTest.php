<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Collector;

use Drupal\Core\Block\BlockManager;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\drupal_developer_assistant\Collector\BlockPluginCollector;
use Drupal\drupal_developer_assistant\Model\PluginComponent;
use Drupal\drupal_developer_assistant\Resolver\ReflectionSourcePathResolver;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Block plugin definition normalization without booting Drupal.
 */
#[Group('drupal_developer_assistant')]
final class BlockPluginCollectorTest extends UnitTestCase {

  /**
   * Tests normalized and sorted Block plugin records.
   */
  public function testCollectsBlockPlugins(): void {
    $block_manager = $this->createMock(BlockManagerInterface::class);
    $block_manager->method('getDefinitions')->willReturn([
      'zeta' => [
        'class' => 'Drupal\\example\\Plugin\\Block\\ZetaBlock',
        'provider' => 'example',
        'admin_label' => 'Zeta block',
      ],
      'alpha' => [
        'class' => BlockManager::class,
        'provider' => 'system',
        'admin_label' => 'Alpha block',
      ],
    ]);

    $collector = new BlockPluginCollector(
      $block_manager,
      new ReflectionSourcePathResolver(DRUPAL_ROOT),
    );

    $this->assertSame('plugins.block', $collector->id());
    $this->assertEquals([
      new PluginComponent(
        pluginType: 'block',
        pluginId: 'alpha',
        label: 'Alpha block',
        className: BlockManager::class,
        provider: 'system',
        sourcePath: 'core/lib/Drupal/Core/Block/BlockManager.php',
      ),
      new PluginComponent(
        pluginType: 'block',
        pluginId: 'zeta',
        label: 'Zeta block',
        className: 'Drupal\\example\\Plugin\\Block\\ZetaBlock',
        provider: 'example',
        sourcePath: NULL,
      ),
    ], $collector->collect());
  }

}
