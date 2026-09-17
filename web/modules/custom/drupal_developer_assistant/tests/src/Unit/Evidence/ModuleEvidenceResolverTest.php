<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Evidence;

use Drupal\drupal_developer_assistant\Evidence\ModuleEvidenceResolver;
use Drupal\drupal_developer_assistant\Model\ModuleArchitecture;
use Drupal\drupal_developer_assistant\Model\ModuleComponent;
use Drupal\drupal_developer_assistant\Model\ModuleSourceFileComponent;
use Drupal\drupal_developer_assistant\Model\PhpFileAnalysis;
use Drupal\drupal_developer_assistant\Model\PhpMethod;
use Drupal\drupal_developer_assistant\Model\PhpSymbol;
use Drupal\drupal_developer_assistant\Retrieval\SourceRetrievalLimits;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests safe resolution of opaque source-evidence identifiers.
 */
#[Group('drupal_developer_assistant')]
final class ModuleEvidenceResolverTest extends UnitTestCase {

  /**
   * Temporary Drupal application root.
   */
  private string $appRoot;

  /**
   * Module source created for the test.
   */
  private string $source;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->appRoot = sys_get_temp_dir()
      . '/dda-evidence-'
      . bin2hex(random_bytes(8));
    $directory = $this->appRoot . '/modules/custom/example/src';
    mkdir($directory, 0777, TRUE);
    $this->source = implode("\n", [
      '<?php',
      '',
      'namespace Drupal\\example;',
      '',
      'final class ExampleService {',
      '  public function run(): void {',
      '  }',
      '}',
      '',
    ]);
    file_put_contents($directory . '/ExampleService.php', $this->source);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    unlink(
      $this->appRoot
      . '/modules/custom/example/src/ExampleService.php',
    );
    rmdir($this->appRoot . '/modules/custom/example/src');
    rmdir($this->appRoot . '/modules/custom/example');
    rmdir($this->appRoot . '/modules/custom');
    rmdir($this->appRoot . '/modules');
    rmdir($this->appRoot);
    parent::tearDown();
  }

  /**
   * Tests resolving a known method to a bounded numbered source preview.
   */
  public function testResolvesKnownMethod(): void {
    $resolver = $this->resolver();
    $symbol = 'Drupal\example\ExampleService::run';
    $evidence = $resolver->resolve(
      $this->architecture(),
      $resolver->referenceId('example', 'src/ExampleService.php', $symbol),
    );

    $this->assertNotNull($evidence);
    $this->assertSame('src/ExampleService.php', $evidence->sourcePath);
    $this->assertSame($symbol, $evidence->symbol);
    $this->assertSame('method', $evidence->kind);
    $this->assertSame(3, $evidence->startLine);
    $this->assertSame(9, $evidence->endLine);
    $this->assertNotNull($evidence->content);
    $this->assertStringContainsString(
      '6 |   public function run(): void {',
      $evidence->content,
    );
    $this->assertTrue($evidence->truncated);
  }

  /**
   * Tests that an ID derived from an arbitrary path cannot read that path.
   */
  public function testRejectsUndiscoveredPath(): void {
    $resolver = $this->resolver();
    $forged_id = $resolver->referenceId(
      'example',
      '../../sites/default/settings.php',
      '',
    );

    $this->assertNull($resolver->resolve(
      $this->architecture(),
      $forged_id,
    ));
  }

  /**
   * Creates the resolver under test.
   */
  private function resolver(): ModuleEvidenceResolver {
    return new ModuleEvidenceResolver(
      $this->appRoot,
      new SourceRetrievalLimits(),
    );
  }

  /**
   * Creates architecture containing one known PHP method.
   */
  private function architecture(): ModuleArchitecture {
    $source_file = new ModuleSourceFileComponent(
      moduleId: 'example',
      relativePath: 'src/ExampleService.php',
      fileType: 'PHP',
      category: 'Source code',
      size: strlen($this->source),
      sourcePath: 'modules/custom/example/src/ExampleService.php',
    );
    $method = new PhpMethod(
      name: 'run',
      visibility: 'public',
      static: FALSE,
      abstract: FALSE,
      final: FALSE,
      returnsByReference: FALSE,
      returnType: 'void',
      parameters: [],
      attributes: [],
      startLine: 6,
      endLine: 7,
    );
    $symbol = new PhpSymbol(
      kind: 'class',
      name: 'ExampleService',
      fullyQualifiedName: 'Drupal\example\ExampleService',
      modifiers: ['final'],
      extends: [],
      implements: [],
      traits: [],
      attributes: [],
      properties: [],
      methods: [$method],
      startLine: 5,
      endLine: 8,
    );

    return new ModuleArchitecture(
      module: new ModuleComponent(
        id: 'example',
        label: 'Example',
        sourcePath: 'modules/custom/example',
        package: 'Custom',
        version: NULL,
        dependencies: [],
      ),
      components: [],
      relationships: [],
      sourceFiles: [$source_file],
      phpFiles: [
        new PhpFileAnalysis(
          sourceFile: $source_file,
          namespaces: ['Drupal\example'],
          imports: [],
          symbols: [$symbol],
          functions: [],
          error: NULL,
        ),
      ],
    );
  }

}
