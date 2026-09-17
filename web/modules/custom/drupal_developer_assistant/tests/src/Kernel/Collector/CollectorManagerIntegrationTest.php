<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Kernel\Collector;

use Drupal\drupal_developer_assistant\Collector\ActionPluginCollector;
use Drupal\drupal_developer_assistant\Collector\BlockPluginCollector;
use Drupal\drupal_developer_assistant\Collector\CollectorManagerInterface;
use Drupal\drupal_developer_assistant\Collector\ConfigurationCollector;
use Drupal\drupal_developer_assistant\Collector\ControllerCollector;
use Drupal\drupal_developer_assistant\Collector\EntityTypeCollector;
use Drupal\drupal_developer_assistant\Collector\FormCollector;
use Drupal\drupal_developer_assistant\Collector\HookCollector;
use Drupal\drupal_developer_assistant\Collector\ModuleCollector;
use Drupal\drupal_developer_assistant\Collector\NavigationCollector;
use Drupal\drupal_developer_assistant\Collector\PluginManagerCollector;
use Drupal\drupal_developer_assistant\Collector\RouteCollector;
use Drupal\drupal_developer_assistant\Collector\ServiceCollector;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Drupal integration for the collector manager.
 */
#[Group('drupal_developer_assistant')]
#[RunTestsInSeparateProcesses]
final class CollectorManagerIntegrationTest extends KernelTestBase {

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
   * Tests that tagged collectors are registered with the manager.
   */
  public function testTaggedCollectorRegistration(): void {
    $manager = $this->container->get(CollectorManagerInterface::class);

    $this->assertInstanceOf(CollectorManagerInterface::class, $manager);
    $this->assertInstanceOf(ModuleCollector::class, $manager->get('modules'));
    $this->assertInstanceOf(ServiceCollector::class, $manager->get('services'));
    $this->assertInstanceOf(
      PluginManagerCollector::class,
      $manager->get('plugin_managers'),
    );
    $this->assertInstanceOf(
      BlockPluginCollector::class,
      $manager->get('plugins.block'),
    );
    $this->assertInstanceOf(
      ActionPluginCollector::class,
      $manager->get('plugins.action'),
    );
    $this->assertInstanceOf(
      EntityTypeCollector::class,
      $manager->get('entity_types'),
    );
    $this->assertInstanceOf(RouteCollector::class, $manager->get('routes'));
    $this->assertInstanceOf(
      NavigationCollector::class,
      $manager->get('navigation'),
    );
    $this->assertInstanceOf(
      ControllerCollector::class,
      $manager->get('controllers'),
    );
    $this->assertInstanceOf(FormCollector::class, $manager->get('forms'));
    $this->assertInstanceOf(HookCollector::class, $manager->get('hooks'));
    $this->assertInstanceOf(
      ConfigurationCollector::class,
      $manager->get('configuration'),
    );
    $this->assertSame(
      [
        'modules',
        'services',
        'plugin_managers',
        'plugins.block',
        'plugins.action',
        'entity_types',
        'routes',
        'navigation',
        'controllers',
        'forms',
        'hooks',
        'configuration',
      ],
      array_keys($manager->all()),
    );
  }

}
