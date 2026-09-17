<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Component;

use Drupal\drupal_developer_assistant\Architecture\ModuleArchitectureBuilderInterface;
use Drupal\drupal_developer_assistant\Component\ModuleComponentDetailsResolver;
use Drupal\drupal_developer_assistant\Model\ComponentRelationship;
use Drupal\drupal_developer_assistant\Model\ModuleArchitecture;
use Drupal\drupal_developer_assistant\Model\ModuleArchitectureComponent;
use Drupal\drupal_developer_assistant\Model\ModuleComponent;
use Drupal\drupal_developer_assistant\Model\ModuleSourceFileComponent;
use Drupal\drupal_developer_assistant\Model\PhpFileAnalysis;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests opaque module-component detail resolution without booting Drupal.
 */
#[Group('drupal_developer_assistant')]
final class ModuleComponentDetailsResolverTest extends UnitTestCase {

  /**
   * Tests resolving a discovered component and its local relationships.
   */
  public function testResolvesKnownComponent(): void {
    $component = new ModuleArchitectureComponent(
      type: 'service',
      id: 'example.worker',
      label: 'Drupal\example\ExampleWorker',
      sourcePath: 'modules/custom/example/src/ExampleWorker.php',
    );
    $outgoing = new ComponentRelationship(
      'service',
      'example.worker',
      'depends_on',
      'service',
      'logger.factory',
    );
    $incoming = new ComponentRelationship(
      'route',
      'example.run',
      'invokes',
      'service',
      'example.worker',
    );
    $unrelated = new ComponentRelationship(
      'module',
      'example',
      'depends_on',
      'module',
      'system',
    );
    $source_file = new ModuleSourceFileComponent(
      moduleId: 'example',
      relativePath: 'src/ExampleWorker.php',
      fileType: 'PHP',
      category: 'Source code',
      size: 100,
      sourcePath: 'modules/custom/example/src/ExampleWorker.php',
    );
    $php_file = new PhpFileAnalysis(
      sourceFile: $source_file,
      namespaces: ['Drupal\example'],
      imports: [],
      symbols: [],
      functions: [],
      error: NULL,
    );
    $architecture = new ModuleArchitecture(
      module: new ModuleComponent(
        id: 'example',
        label: 'Example',
        sourcePath: 'modules/custom/example',
        package: 'Custom',
        version: NULL,
        dependencies: [],
      ),
      components: [$component],
      relationships: [$outgoing, $incoming, $unrelated],
      sourceFiles: [$source_file],
      phpFiles: [$php_file],
    );
    $builder = $this->createMock(ModuleArchitectureBuilderInterface::class);
    $builder->method('build')->with('example')->willReturn($architecture);
    $resolver = new ModuleComponentDetailsResolver($builder);

    $reference = $resolver->referenceId(
      'example',
      'service',
      'example.worker',
    );
    $details = $resolver->resolve('example', $reference);

    $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $reference);
    $this->assertNotNull($details);
    $this->assertSame($component, $details->component);
    $this->assertSame([$incoming], $details->incomingRelationships);
    $this->assertSame([$outgoing], $details->outgoingRelationships);
    $this->assertSame([$php_file], $details->phpFiles);
    $this->assertNull($resolver->resolve('example', 'not-a-reference'));
  }

}
