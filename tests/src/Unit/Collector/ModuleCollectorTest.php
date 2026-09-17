<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Collector;

use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\InfoParserInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\drupal_developer_assistant\Collector\ModuleCollector;
use Drupal\drupal_developer_assistant\Model\ModuleComponent;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests module metadata normalization without booting Drupal.
 */
#[Group('drupal_developer_assistant')]
final class ModuleCollectorTest extends UnitTestCase {

  /**
   * Tests that parsed info.yml metadata becomes a typed module record.
   */
  public function testCollectsModuleMetadata(): void {
    $extension = $this->createMock(Extension::class);
    $extension->method('getPathname')
      ->willReturn('modules/custom/example/example.info.yml');
    $extension->method('getPath')
      ->willReturn('modules/custom/example');

    $module_handler = $this->createMock(ModuleHandlerInterface::class);
    $module_handler->method('getModuleList')
      ->willReturn(['example' => $extension]);

    $info_parser = $this->createMock(InfoParserInterface::class);
    $info_parser->expects($this->once())
      ->method('parse')
      ->with('modules/custom/example/example.info.yml')
      ->willReturn([
        'name' => 'Example',
        'package' => 'Custom',
        'version' => '1.2.3',
        'dependencies' => ['drupal:system', 'drupal:user'],
      ]);

    $collector = new ModuleCollector($module_handler, $info_parser);
    $records = $collector->collect();

    $this->assertCount(1, $records);
    $this->assertEquals(
      new ModuleComponent(
        id: 'example',
        label: 'Example',
        sourcePath: 'modules/custom/example',
        package: 'Custom',
        version: '1.2.3',
        dependencies: ['drupal:system', 'drupal:user'],
      ),
      $records[0],
    );
  }

}
