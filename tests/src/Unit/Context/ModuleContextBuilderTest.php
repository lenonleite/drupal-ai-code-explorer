<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Context;

use Drupal\drupal_developer_assistant\Context\ModuleContextBuilder;
use Drupal\drupal_developer_assistant\Context\ModuleContextLimits;
use Drupal\drupal_developer_assistant\Model\ComponentRelationship;
use Drupal\drupal_developer_assistant\Model\ModuleArchitecture;
use Drupal\drupal_developer_assistant\Model\ModuleArchitectureComponent;
use Drupal\drupal_developer_assistant\Model\ModuleComponent;
use Drupal\drupal_developer_assistant\Model\ModuleSourceFileComponent;
use Drupal\drupal_developer_assistant\Model\PhpFileAnalysis;
use Drupal\drupal_developer_assistant\Model\PhpFunction;
use Drupal\drupal_developer_assistant\Model\PhpMethod;
use Drupal\drupal_developer_assistant\Model\PhpParameter;
use Drupal\drupal_developer_assistant\Model\PhpProperty;
use Drupal\drupal_developer_assistant\Model\PhpSymbol;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests architecture-to-context normalization without booting Drupal.
 */
#[Group('drupal_developer_assistant')]
final class ModuleContextBuilderTest extends UnitTestCase {

  /**
   * Tests conversion to primitive, JSON-ready architectural facts.
   */
  public function testBuildsModuleContext(): void {
    $parameter = new PhpParameter(
      name: 'logger',
      type: 'Psr\\Log\\LoggerInterface',
      byReference: FALSE,
      variadic: FALSE,
      promoted: TRUE,
      hasDefault: TRUE,
      attributes: ['SensitiveParameter'],
    );
    $source_file = new ModuleSourceFileComponent(
      moduleId: 'example',
      relativePath: 'src/ExampleService.php',
      fileType: 'PHP',
      category: 'Source code',
      size: 512,
      sourcePath: 'modules/custom/example/src/ExampleService.php',
    );
    $php_file = new PhpFileAnalysis(
      sourceFile: $source_file,
      namespaces: ['Drupal\\example'],
      imports: ['LoggerInterface' => 'Psr\\Log\\LoggerInterface'],
      symbols: [
        new PhpSymbol(
          kind: 'class',
          name: 'ExampleService',
          fullyQualifiedName: 'Drupal\\example\\ExampleService',
          modifiers: ['final', 'readonly'],
          extends: [],
          implements: ['Drupal\\example\\ExampleServiceInterface'],
          traits: ['Drupal\\example\\ExampleTrait'],
          attributes: [],
          properties: [
            new PhpProperty(
              name: 'logger',
              visibility: 'private',
              type: 'Psr\\Log\\LoggerInterface',
              static: FALSE,
              readonly: TRUE,
              attributes: [],
            ),
          ],
          methods: [
            new PhpMethod(
              name: '__construct',
              visibility: 'public',
              static: FALSE,
              abstract: FALSE,
              final: FALSE,
              returnsByReference: FALSE,
              returnType: NULL,
              parameters: [$parameter],
              attributes: [],
            ),
          ],
        ),
      ],
      functions: [
        new PhpFunction(
          name: 'example_helper',
          fullyQualifiedName: 'Drupal\\example\\example_helper',
          returnsByReference: FALSE,
          returnType: 'void',
          parameters: [],
          attributes: [],
        ),
      ],
      error: NULL,
    );
    $relationship = new ComponentRelationship(
      sourceType: 'class',
      sourceId: 'Drupal\\example\\ExampleService',
      relationship: 'depends_on',
      targetType: 'service',
      targetId: 'logger.factory',
      metadata: [
        'context' => 'constructor',
        'parameter' => '$logger',
      ],
    );
    $architecture = new ModuleArchitecture(
      module: new ModuleComponent(
        id: 'example',
        label: 'Example',
        sourcePath: 'modules/custom/example',
        package: 'Custom',
        version: '1.0.0',
        dependencies: ['drupal:system'],
      ),
      components: [
        new ModuleArchitectureComponent(
          type: 'service',
          id: 'example.service',
          label: 'Drupal\\example\\ExampleService',
          sourcePath: 'modules/custom/example/src/ExampleService.php',
        ),
      ],
      relationships: [$relationship],
      sourceFiles: [$source_file],
      phpFiles: [$php_file],
    );

    $context = (new ModuleContextBuilder(new ModuleContextLimits()))
      ->build($architecture);
    $data = $context->jsonSerialize();

    $this->assertSame([
      'id' => 'example',
      'name' => 'Example',
      'path' => 'modules/custom/example',
      'package' => 'Custom',
      'version' => '1.0.0',
      'dependencies' => ['drupal:system'],
    ], $data['module']);
    $this->assertSame([
      'available' => [
        'architecture_components' => 1,
        'source_files' => 1,
        'php_files' => 1,
        'php_symbols' => 1,
        'php_properties' => 1,
        'php_methods' => 1,
        'php_functions' => 1,
        'php_parameters' => 1,
        'relationships' => 1,
      ],
      'included' => [
        'architecture_components' => 1,
        'source_files' => 1,
        'php_files' => 1,
        'php_symbols' => 1,
        'php_properties' => 1,
        'php_methods' => 1,
        'php_functions' => 1,
        'php_parameters' => 1,
        'relationships' => 1,
      ],
      'omitted' => [
        'architecture_components' => 0,
        'source_files' => 0,
        'php_files' => 0,
        'php_symbols' => 0,
        'php_properties' => 0,
        'php_methods' => 0,
        'php_functions' => 0,
        'php_parameters' => 0,
        'relationships' => 0,
      ],
      'limits' => [
        'architecture_components' => 100,
        'source_files' => 150,
        'php_files' => 30,
        'symbols_per_file' => 10,
        'properties_per_symbol' => 25,
        'methods_per_symbol' => 25,
        'functions_per_file' => 20,
        'parameters_per_callable' => 20,
        'relationships' => 200,
        'string_bytes' => 500,
      ],
      'strings_shortened' => 0,
      'truncated' => FALSE,
    ], $data['summary']);
    $this->assertCount(2, $data['components']);
    $this->assertSame('service', $data['components'][0]['type']);
    $this->assertSame('source_file', $data['components'][1]['type']);
    $this->assertSame(512, $data['components'][1]['size_bytes']);
    $this->assertSame(
      'Drupal\\example\\ExampleService',
      $data['php_structure'][0]['symbols'][0]['fully_qualified_name'],
    );
    $normalized_symbol = $data['php_structure'][0]['symbols'][0];
    $normalized_parameter = $normalized_symbol['methods'][0]['parameters'][0];
    $this->assertSame('Psr\\Log\\LoggerInterface', $normalized_parameter['type']);
    $this->assertTrue($normalized_parameter['has_default']);
    $this->assertArrayNotHasKey('default', $normalized_parameter);
    $this->assertArrayNotHasKey('default_value', $normalized_parameter);
    $this->assertSame([
      'source' => [
        'type' => 'class',
        'id' => 'Drupal\\example\\ExampleService',
      ],
      'relationship' => 'depends_on',
      'target' => [
        'type' => 'service',
        'id' => 'logger.factory',
      ],
      'metadata' => [
        'context' => 'constructor',
        'parameter' => '$logger',
      ],
    ], $data['relationships'][0]);
    $this->assertJson(json_encode($context, JSON_THROW_ON_ERROR));
  }

  /**
   * Tests visible omissions, string limits, and production-file priority.
   */
  public function testAppliesContextLimits(): void {
    $test_file = new ModuleSourceFileComponent(
      moduleId: 'example',
      relativePath: 'tests/src/ExampleTest.php',
      fileType: 'PHP',
      category: 'Test',
      size: 100,
      sourcePath: 'modules/custom/example/tests/src/ExampleTest.php',
    );
    $source_file = new ModuleSourceFileComponent(
      moduleId: 'example',
      relativePath: 'src/Example.php',
      fileType: 'PHP',
      category: 'Source code',
      size: 100,
      sourcePath: 'modules/custom/example/src/Example.php',
    );
    $components = [
      new ModuleArchitectureComponent('service', 'one', 'One', NULL),
      new ModuleArchitectureComponent('service', 'two', 'Two', NULL),
    ];
    $relationships = [
      new ComponentRelationship('service', 'one', 'depends_on', 'service', 'two'),
      new ComponentRelationship('service', 'two', 'depends_on', 'service', 'one'),
    ];
    $architecture = new ModuleArchitecture(
      module: new ModuleComponent(
        id: 'example',
        label: 'Example module with a long name',
        sourcePath: 'modules/custom/example',
        package: NULL,
        version: NULL,
        dependencies: [],
      ),
      components: $components,
      relationships: $relationships,
      sourceFiles: [$test_file, $source_file],
      phpFiles: [
        new PhpFileAnalysis($test_file, [], [], [], [], NULL),
        new PhpFileAnalysis($source_file, [], [], [], [], NULL),
      ],
    );
    $limits = new ModuleContextLimits(
      maxArchitectureComponents: 1,
      maxSourceFiles: 1,
      maxPhpFiles: 1,
      maxSymbolsPerFile: 0,
      maxPropertiesPerSymbol: 0,
      maxMethodsPerSymbol: 0,
      maxFunctionsPerFile: 0,
      maxParametersPerCallable: 0,
      maxRelationships: 1,
      maxStringBytes: 12,
    );

    $data = (new ModuleContextBuilder($limits))
      ->build($architecture)
      ->jsonSerialize();

    $this->assertSame('Example m...', $data['module']['name']);
    $this->assertCount(2, $data['components']);
    $this->assertCount(1, $data['php_structure']);
    $this->assertSame(
      'src/Examp...',
      $data['php_structure'][0]['path'],
    );
    $this->assertCount(1, $data['relationships']);
    $this->assertSame(2, $data['summary']['available']['php_files']);
    $this->assertSame(1, $data['summary']['included']['php_files']);
    $this->assertSame(1, $data['summary']['omitted']['php_files']);
    $this->assertGreaterThan(0, $data['summary']['strings_shortened']);
    $this->assertTrue($data['summary']['truncated']);
  }

}
