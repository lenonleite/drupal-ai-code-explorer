<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Context;

use Drupal\drupal_developer_assistant\Context\ModuleContext;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the provider-independent module context contract.
 */
#[Group('drupal_developer_assistant')]
final class ModuleContextTest extends UnitTestCase {

  /**
   * Tests the stable top-level JSON structure.
   */
  public function testSerializesContextContract(): void {
    $context = new ModuleContext(
      module: [
        'id' => 'example',
        'name' => 'Example',
        'path' => 'modules/custom/example',
        'package' => 'Custom',
        'version' => '1.0.0',
        'dependencies' => ['drupal:system'],
      ],
      summary: [
        'available' => ['components' => 1],
        'included' => ['components' => 1],
        'omitted' => ['components' => 0],
        'truncated' => FALSE,
      ],
      components: [
        [
          'type' => 'service',
          'id' => 'example.manager',
        ],
      ],
      phpStructure: [
        [
          'path' => 'src/ExampleManager.php',
          'symbols' => ['Drupal\\example\\ExampleManager'],
        ],
      ],
      relationships: [
        [
          'source' => 'Drupal\\example\\ExampleManager',
          'relationship' => 'depends_on',
          'target' => 'logger.factory',
        ],
      ],
    );

    $expected = [
      'schema_version' => '1.3',
      'module' => $context->module,
      'summary' => $context->summary,
      'components' => $context->components,
      'php_structure' => $context->phpStructure,
      'relationships' => $context->relationships,
    ];

    $this->assertSame($expected, $context->jsonSerialize());
    $this->assertSame(
      json_encode($expected, JSON_THROW_ON_ERROR),
      json_encode($context, JSON_THROW_ON_ERROR),
    );
  }

}
