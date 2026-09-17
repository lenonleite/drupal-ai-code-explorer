<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Relationship;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\drupal_developer_assistant\Collector\ConfigurationCollector;
use Drupal\drupal_developer_assistant\Collector\EntityTypeCollector;
use Drupal\drupal_developer_assistant\Model\ComponentRelationship;
use Drupal\drupal_developer_assistant\Relationship\ConfigurationRelationshipResolver;
use Drupal\drupal_developer_assistant\Resolver\SourcePathResolverInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests configuration dependency relationships without booting Drupal.
 */
#[Group('drupal_developer_assistant')]
final class ConfigurationRelationshipResolverTest extends UnitTestCase {

  /**
   * Tests direct, enforced, and unknown configuration dependency types.
   */
  public function testResolvesConfigurationDependencies(): void {
    $configuration = $this->createMock(ImmutableConfig::class);
    $configuration->method('getRawData')->willReturn([
      'dependencies' => [
        'module' => ['example'],
        'config' => ['example.settings'],
        'theme' => ['stark'],
        'content' => ['node:article:uuid'],
        'package' => ['external_package'],
        'enforced' => [
          'config' => ['required.settings'],
          'module' => ['required_module'],
        ],
      ],
    ]);

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('listAll')->willReturn(['example.item']);
    $config_factory->method('loadMultiple')->willReturn([
      'example.item' => $configuration,
    ]);

    $config_manager = $this->createMock(ConfigManagerInterface::class);
    $config_manager->method('getEntityTypeIdByName')->willReturn(NULL);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getDefinitions')->willReturn([]);
    $source_path_resolver = $this->createMock(
      SourcePathResolverInterface::class,
    );

    $collector = new ConfigurationCollector(
      $config_factory,
      $config_manager,
      new EntityTypeCollector(
        $entity_type_manager,
        $source_path_resolver,
      ),
    );
    $resolver = new ConfigurationRelationshipResolver($collector);

    $this->assertSame('configuration', $resolver->id());
    $this->assertEquals([
      new ComponentRelationship(
        sourceType: 'configuration',
        sourceId: 'example.item',
        relationship: 'depends_on',
        targetType: 'configuration',
        targetId: 'example.settings',
        metadata: ['dependency_type' => 'config'],
      ),
      new ComponentRelationship(
        sourceType: 'configuration',
        sourceId: 'example.item',
        relationship: 'depends_on',
        targetType: 'content',
        targetId: 'node:article:uuid',
        metadata: ['dependency_type' => 'content'],
      ),
      new ComponentRelationship(
        sourceType: 'configuration',
        sourceId: 'example.item',
        relationship: 'depends_on',
        targetType: 'configuration',
        targetId: 'required.settings',
        metadata: [
          'dependency_type' => 'config',
          'enforced' => 'true',
        ],
      ),
      new ComponentRelationship(
        sourceType: 'configuration',
        sourceId: 'example.item',
        relationship: 'depends_on',
        targetType: 'module',
        targetId: 'required_module',
        metadata: [
          'dependency_type' => 'module',
          'enforced' => 'true',
        ],
      ),
      new ComponentRelationship(
        sourceType: 'configuration',
        sourceId: 'example.item',
        relationship: 'depends_on',
        targetType: 'module',
        targetId: 'example',
        metadata: ['dependency_type' => 'module'],
      ),
      new ComponentRelationship(
        sourceType: 'configuration',
        sourceId: 'example.item',
        relationship: 'depends_on',
        targetType: 'configuration_dependency',
        targetId: 'external_package',
        metadata: ['dependency_type' => 'package'],
      ),
      new ComponentRelationship(
        sourceType: 'configuration',
        sourceId: 'example.item',
        relationship: 'depends_on',
        targetType: 'theme',
        targetId: 'stark',
        metadata: ['dependency_type' => 'theme'],
      ),
    ], $resolver->resolve());
  }

}
