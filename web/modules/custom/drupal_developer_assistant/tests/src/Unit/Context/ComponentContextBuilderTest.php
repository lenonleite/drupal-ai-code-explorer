<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Context;

use Drupal\drupal_developer_assistant\Context\ComponentContextBuilder;
use Drupal\drupal_developer_assistant\Context\ModuleContextBuilder;
use Drupal\drupal_developer_assistant\Context\ModuleContextLimits;
use Drupal\drupal_developer_assistant\Model\ComponentRelationship;
use Drupal\drupal_developer_assistant\Model\ModuleArchitectureComponent;
use Drupal\drupal_developer_assistant\Model\ModuleComponent;
use Drupal\drupal_developer_assistant\Model\ModuleComponentDetails;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests selection and normalization of one component's AI context.
 */
#[Group('drupal_developer_assistant')]
final class ComponentContextBuilderTest extends UnitTestCase {

  /**
   * Tests that relationship direction remains explicit in the context.
   */
  public function testBuildsDirectedComponentContext(): void {
    $details = new ModuleComponentDetails(
      module: new ModuleComponent(
        id: 'example',
        label: 'Example',
        sourcePath: 'modules/custom/example',
        package: 'Custom',
        version: NULL,
        dependencies: [],
      ),
      component: new ModuleArchitectureComponent(
        type: 'service',
        id: 'example.worker',
        label: 'Example worker',
        sourcePath: 'modules/custom/example/src/ExampleWorker.php',
      ),
      incomingRelationships: [
        new ComponentRelationship(
          'route',
          'example.run',
          'invokes',
          'service',
          'example.worker',
        ),
      ],
      outgoingRelationships: [
        new ComponentRelationship(
          'service',
          'example.worker',
          'depends_on',
          'service',
          'logger.factory',
        ),
      ],
      phpFiles: [],
    );
    $builder = new ComponentContextBuilder(
      new ModuleContextBuilder(new ModuleContextLimits()),
    );

    $data = $builder->build($details)->jsonSerialize();

    $this->assertSame('1.1', $data['schema_version']);
    $this->assertSame('example', $data['module']['id']);
    $this->assertSame('example.worker', $data['component']['id']);
    $this->assertSame(
      'context.component',
      $data['component']['evidence_reference'],
    );
    $this->assertSame(
      'depends_on',
      $data['outgoing_relationships'][0]['relationship'],
    );
    $this->assertSame(
      'context.outgoing_relationships[0]',
      $data['outgoing_relationships'][0]['evidence_reference'],
    );
    $this->assertSame(
      'invokes',
      $data['incoming_relationships'][0]['relationship'],
    );
    $this->assertSame(
      'context.incoming_relationships[0]',
      $data['incoming_relationships'][0]['evidence_reference'],
    );
    $this->assertSame([], $data['php_structure']);
  }

}
