<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Collector;

use Drupal\Core\DrupalKernelInterface;
use Drupal\drupal_developer_assistant\Collector\ServiceCollector;
use Drupal\drupal_developer_assistant\Model\ServiceComponent;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests service definition normalization without booting Drupal.
 */
#[Group('drupal_developer_assistant')]
final class ServiceCollectorTest extends UnitTestCase {

  /**
   * Tests concrete services and aliases from a compiled definition.
   */
  public function testCollectsServicesAndAliases(): void {
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
            'value' => [
              $service_reference('logger'),
              (object) [
                'type' => 'iterator',
                'value' => [$service_reference('lazy.service')],
              ],
              (object) [
                'type' => 'private_service',
                'id' => 'private.example',
                'value' => [
                  'arguments' => $service_reference('not.direct'),
                ],
              ],
            ],
          ],
          'properties' => $service_reference('state.service'),
          'calls' => [
            ['addResolver', [$service_reference('resolver.service')]],
          ],
          'factory' => [$service_reference('factory.builder'), 'create'],
          'configurator' => [
            $service_reference('service.configurator'),
            'configure',
          ],
        ]),
        'factory.service' => serialize([
          'factory' => 'Drupal\\example\\Factory::create',
        ]),
      ],
      'aliases' => [
        'example.interface' => 'example.service',
        'example.nested_alias' => 'example.interface',
      ],
    ]);

    $collector = new ServiceCollector($kernel);

    $this->assertSame('services', $collector->id());
    $this->assertEquals([
      new ServiceComponent(
        id: 'example.interface',
        className: 'Drupal\\example\\ExampleService',
        aliasTarget: 'example.service',
        references: [],
      ),
      new ServiceComponent(
        id: 'example.nested_alias',
        className: 'Drupal\\example\\ExampleService',
        aliasTarget: 'example.interface',
        references: [],
      ),
      new ServiceComponent(
        id: 'example.service',
        className: 'Drupal\\example\\ExampleService',
        aliasTarget: NULL,
        references: [
          'constructor' => [
            'lazy.service',
            'logger',
            'private.example',
          ],
          'property' => ['state.service'],
          'method_call' => ['resolver.service'],
          'factory' => ['factory.builder'],
          'configurator' => ['service.configurator'],
        ],
      ),
      new ServiceComponent(
        id: 'factory.service',
        className: NULL,
        aliasTarget: NULL,
        references: [],
      ),
    ], $collector->collect());
  }

  /**
   * Tests collection before a cached container definition is available.
   */
  public function testMissingCachedDefinitionReturnsNoRecords(): void {
    $kernel = $this->createMock(DrupalKernelInterface::class);
    $kernel->method('getCachedContainerDefinition')->willReturn(NULL);

    $collector = new ServiceCollector($kernel);

    $this->assertSame([], $collector->collect());
  }

}
