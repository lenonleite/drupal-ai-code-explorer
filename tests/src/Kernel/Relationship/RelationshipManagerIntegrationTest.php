<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Kernel\Relationship;

use Drupal\drupal_developer_assistant\Relationship\ConfigurationRelationshipResolver;
use Drupal\drupal_developer_assistant\Relationship\EntityRelationshipResolver;
use Drupal\drupal_developer_assistant\Relationship\ModuleRelationshipResolver;
use Drupal\drupal_developer_assistant\Relationship\NavigationRelationshipResolver;
use Drupal\drupal_developer_assistant\Relationship\RelationshipManagerInterface;
use Drupal\drupal_developer_assistant\Relationship\RouteRelationshipResolver;
use Drupal\drupal_developer_assistant\Relationship\ServiceRelationshipResolver;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Drupal integration for the relationship manager.
 */
#[Group('drupal_developer_assistant')]
#[RunTestsInSeparateProcesses]
final class RelationshipManagerIntegrationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'file',
    'key',
    'ai',
    'drupal_developer_assistant',
  ];

  /**
   * Tests that tagged relationship resolvers are registered.
   */
  public function testTaggedResolverRegistration(): void {
    $manager = $this->container->get(RelationshipManagerInterface::class);

    $this->assertInstanceOf(RelationshipManagerInterface::class, $manager);
    $this->assertInstanceOf(
      RouteRelationshipResolver::class,
      $manager->get('routes'),
    );
    $this->assertInstanceOf(
      NavigationRelationshipResolver::class,
      $manager->get('navigation'),
    );
    $this->assertInstanceOf(
      EntityRelationshipResolver::class,
      $manager->get('entities'),
    );
    $this->assertInstanceOf(
      ModuleRelationshipResolver::class,
      $manager->get('modules'),
    );
    $this->assertInstanceOf(
      ConfigurationRelationshipResolver::class,
      $manager->get('configuration'),
    );
    $this->assertInstanceOf(
      ServiceRelationshipResolver::class,
      $manager->get('services'),
    );
    $this->assertSame(
      [
        'routes',
        'navigation',
        'entities',
        'modules',
        'configuration',
        'services',
      ],
      array_keys($manager->all()),
    );
  }

}
