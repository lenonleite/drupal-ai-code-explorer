<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Collector;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\drupal_developer_assistant\Collector\HookCollector;
use Drupal\drupal_developer_assistant\Model\HookComponent;
use Drupal\drupal_developer_assistant\Resolver\SourcePathResolverInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests compiled hook-map normalization without booting Drupal.
 */
#[Group('drupal_developer_assistant')]
final class HookCollectorTest extends UnitTestCase {

  /**
   * Tests procedural and object-oriented hook implementations and order.
   */
  public function testCollectsHookImplementations(): void {
    $store = $this->createMock(KeyValueStoreInterface::class);
    $store->method('get')->with('hook_list', [])->willReturn([
      'zeta' => [
        'Drupal\\example\\Hook\\ExampleHooks::zeta' => 'example',
        'example_zeta' => 'example',
      ],
      'alpha' => [
        'example_alpha' => 'example',
        'Drupal\\other\\Hook\\OtherHooks::alpha' => 'other',
      ],
    ]);

    $key_value_factory = $this->createMock(KeyValueFactoryInterface::class);
    $key_value_factory->method('get')->with('hook_data')->willReturn($store);

    $source_path_resolver = $this->createMock(
      SourcePathResolverInterface::class,
    );
    $source_path_resolver->method('resolve')->willReturnMap([
      [
        'Drupal\\example\\Hook\\ExampleHooks',
        'modules/example/src/Hook/ExampleHooks.php',
      ],
      [
        'Drupal\\other\\Hook\\OtherHooks',
        'modules/other/src/Hook/OtherHooks.php',
      ],
    ]);
    $source_path_resolver->method('resolveFunction')->willReturnMap([
      ['example_alpha', 'modules/example/example.module'],
      ['example_zeta', 'modules/example/example.module'],
    ]);

    $collector = new HookCollector(
      $key_value_factory,
      $source_path_resolver,
    );

    $this->assertSame('hooks', $collector->id());
    $this->assertEquals([
      new HookComponent(
        hookName: 'alpha',
        provider: 'example',
        implementationType: 'procedural',
        callable: 'example_alpha',
        className: NULL,
        methodName: NULL,
        executionOrder: 1,
        sourcePath: 'modules/example/example.module',
      ),
      new HookComponent(
        hookName: 'alpha',
        provider: 'other',
        implementationType: 'object-oriented',
        callable: 'Drupal\\other\\Hook\\OtherHooks::alpha',
        className: 'Drupal\\other\\Hook\\OtherHooks',
        methodName: 'alpha',
        executionOrder: 2,
        sourcePath: 'modules/other/src/Hook/OtherHooks.php',
      ),
      new HookComponent(
        hookName: 'zeta',
        provider: 'example',
        implementationType: 'object-oriented',
        callable: 'Drupal\\example\\Hook\\ExampleHooks::zeta',
        className: 'Drupal\\example\\Hook\\ExampleHooks',
        methodName: 'zeta',
        executionOrder: 1,
        sourcePath: 'modules/example/src/Hook/ExampleHooks.php',
      ),
      new HookComponent(
        hookName: 'zeta',
        provider: 'example',
        implementationType: 'procedural',
        callable: 'example_zeta',
        className: NULL,
        methodName: NULL,
        executionOrder: 2,
        sourcePath: 'modules/example/example.module',
      ),
    ], $collector->collect());
  }

}
