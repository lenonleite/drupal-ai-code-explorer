<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Kernel\Collector;

use Drupal\drupal_developer_assistant\Collector\CollectorInterface;
use Drupal\drupal_developer_assistant\Collector\ModuleCollector;
use Drupal\drupal_developer_assistant\Model\ModuleComponent;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the enabled-module collector.
 */
#[Group('drupal_developer_assistant')]
#[RunTestsInSeparateProcesses]
final class ModuleCollectorTest extends KernelTestBase {

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
   * Tests the collector service and its normalized module records.
   */
  public function testCollect(): void {
    $collector = $this->container->get(ModuleCollector::class);

    $this->assertInstanceOf(CollectorInterface::class, $collector);
    $this->assertSame('modules', $collector->id());

    $records = $collector->collect();
    $records_by_id = [];
    $ids = [];
    foreach ($records as $record) {
      $this->assertInstanceOf(ModuleComponent::class, $record);
      $records_by_id[$record->id] = $record;
      $ids[] = $record->id;
    }

    $this->assertSame('module', $records_by_id['system']->type);
    $this->assertSame('System', $records_by_id['system']->label);
    $this->assertSame('core/modules/system', $records_by_id['system']->sourcePath);
    $this->assertSame('Core', $records_by_id['system']->package);
    $this->assertSame(\Drupal::VERSION, $records_by_id['system']->version);
    $this->assertSame([], $records_by_id['system']->dependencies);
    $this->assertSame(
      'Drupal Developer Assistant',
      $records_by_id['drupal_developer_assistant']->label,
    );
    $this->assertSame(
      'modules/custom/drupal_developer_assistant',
      $records_by_id['drupal_developer_assistant']->sourcePath,
    );
    $this->assertSame(
      'Development',
      $records_by_id['drupal_developer_assistant']->package,
    );
    $this->assertNull($records_by_id['drupal_developer_assistant']->version);
    $this->assertSame(
      [
        'ai:ai',
        'ai_agents:ai_agents',
        'ai_agents:ai_agents_explorer',
      ],
      $records_by_id['drupal_developer_assistant']->dependencies,
    );

    $sorted_ids = $ids;
    sort($sorted_ids, SORT_STRING);
    $this->assertSame($sorted_ids, $ids);
  }

}
