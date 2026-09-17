<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Relationship;

use Drupal\Core\DrupalKernelInterface;
use Drupal\drupal_developer_assistant\Collector\ServiceCollector;
use Drupal\drupal_developer_assistant\Model\ComponentRelationship;
use Drupal\drupal_developer_assistant\Relationship\ServiceRelationshipResolver;
use Drupal\drupal_developer_assistant\Resolver\SourcePathResolverInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests service relationships without booting Drupal.
 */
#[Group('drupal_developer_assistant')]
final class ServiceRelationshipResolverTest extends UnitTestCase {

  /**
   * Tests aliases, implementation classes, and dependency contexts.
   */
  public function testResolvesServiceRelationships(): void {
    $service_reference = static fn(string $id): \stdClass => (object) [
      'type' => 'service',
      'id' => $id,
    ];
    $kernel = $this->createMock(DrupalKernelInterface::class);
    $kernel->method('getCachedContainerDefinition')->willReturn([
      'services' => [
        'example.service' => serialize([
          'class' => 'Drupal\\example\\ExampleService',
          'arguments' => (object) [
            'type' => 'collection',
            'value' => [$service_reference('logger.channel.default')],
          ],
          'calls' => [
            ['setHelper', [$service_reference('example.helper')]],
          ],
        ]),
      ],
      'aliases' => [
        'example.interface' => 'example.service',
      ],
    ]);

    $source_path_resolver = $this->createMock(
      SourcePathResolverInterface::class,
    );
    $source_path_resolver->method('resolve')->with(
      'Drupal\\example\\ExampleService',
    )->willReturn('modules/example/src/ExampleService.php');

    $resolver = new ServiceRelationshipResolver(
      new ServiceCollector($kernel),
      $source_path_resolver,
    );

    $this->assertSame('services', $resolver->id());
    $this->assertEquals([
      new ComponentRelationship(
        sourceType: 'service',
        sourceId: 'example.interface',
        relationship: 'aliases',
        targetType: 'service',
        targetId: 'example.service',
      ),
      new ComponentRelationship(
        sourceType: 'service',
        sourceId: 'example.service',
        relationship: 'implemented_by',
        targetType: 'class',
        targetId: 'Drupal\\example\\ExampleService',
        metadata: [
          'source_path' => 'modules/example/src/ExampleService.php',
        ],
      ),
      new ComponentRelationship(
        sourceType: 'service',
        sourceId: 'example.service',
        relationship: 'depends_on',
        targetType: 'service',
        targetId: 'logger.channel.default',
        metadata: ['context' => 'constructor'],
      ),
      new ComponentRelationship(
        sourceType: 'service',
        sourceId: 'example.service',
        relationship: 'depends_on',
        targetType: 'service',
        targetId: 'example.helper',
        metadata: ['context' => 'method_call'],
      ),
    ], $resolver->resolve());
  }

}
