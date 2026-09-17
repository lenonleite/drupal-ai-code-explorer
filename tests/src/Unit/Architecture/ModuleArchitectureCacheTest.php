<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Architecture;

use Drupal\Component\Datetime\Time;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\drupal_developer_assistant\Architecture\ModuleArchitectureCache;
use Drupal\drupal_developer_assistant\Model\ModuleArchitecture;
use Drupal\drupal_developer_assistant\Model\ModuleComponent;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests persistent module architecture cache behavior.
 */
#[Group('drupal_developer_assistant')]
final class ModuleArchitectureCacheTest extends UnitTestCase {

  /**
   * Tests cache misses, storage, retrieval, and invalidation.
   */
  public function testCacheLifecycle(): void {
    $cache = new ModuleArchitectureCache(
      new MemoryBackend(new Time()),
    );
    $architecture = new ModuleArchitecture(
      new ModuleComponent(
        id: 'example',
        label: 'Example',
        sourcePath: 'modules/custom/example',
        package: 'Custom',
        version: '1.0.0',
        dependencies: [],
      ),
      [],
      [],
      [],
      [],
    );

    $this->assertNull($cache->get('example'));

    $cache->set('example', $architecture);
    $this->assertEquals($architecture, $cache->get('example'));

    $cache->invalidate('example');
    $this->assertNull($cache->get('example'));
  }

}
