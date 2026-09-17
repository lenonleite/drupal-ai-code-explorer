<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Resolver;

use Drupal\drupal_developer_assistant\Resolver\ReflectionSourcePathResolver;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test function used for source-path reflection.
 */
function reflection_source_path_resolver_test_function(): void {}

/**
 * Tests reflection-based source-path resolution.
 */
#[Group('drupal_developer_assistant')]
final class ReflectionSourcePathResolverTest extends UnitTestCase {

  /**
   * Tests class and function source paths relative to the Drupal root.
   */
  public function testResolvesClassesAndFunctions(): void {
    $resolver = new ReflectionSourcePathResolver(DRUPAL_ROOT);
    $expected = 'modules/custom/drupal_developer_assistant/tests/src/Unit/Resolver/ReflectionSourcePathResolverTest.php';

    $this->assertSame($expected, $resolver->resolve(self::class));
    $this->assertSame(
      $expected,
      $resolver->resolveFunction(
        __NAMESPACE__ . '\\reflection_source_path_resolver_test_function',
      ),
    );
    $this->assertNull($resolver->resolve('Missing\\ExampleClass'));
    $this->assertNull($resolver->resolveFunction('missing_example_function'));
  }

  /**
   * Tests that an optional class with a missing dependency does not crash.
   */
  public function testReturnsNullWhenClassAutoloadingFails(): void {
    $class_name = 'Drupal\\optional\\BrokenHandler';
    $loader = static function (string $requested_class) use ($class_name): void {
      if ($requested_class === $class_name) {
        throw new \Error('The optional parent class is unavailable.');
      }
    };
    spl_autoload_register($loader);

    try {
      $resolver = new ReflectionSourcePathResolver(DRUPAL_ROOT);
      $this->assertNull($resolver->resolve($class_name));
    }
    finally {
      spl_autoload_unregister($loader);
    }
  }

}
