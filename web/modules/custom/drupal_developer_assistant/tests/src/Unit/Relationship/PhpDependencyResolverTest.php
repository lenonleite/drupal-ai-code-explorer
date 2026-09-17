<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Relationship;

use Drupal\drupal_developer_assistant\Model\ComponentRelationship;
use Drupal\drupal_developer_assistant\Model\ModuleSourceFileComponent;
use Drupal\drupal_developer_assistant\Model\PhpFileAnalysis;
use Drupal\drupal_developer_assistant\Model\PhpMethod;
use Drupal\drupal_developer_assistant\Model\PhpParameter;
use Drupal\drupal_developer_assistant\Model\PhpSymbol;
use Drupal\drupal_developer_assistant\Model\ServiceComponent;
use Drupal\drupal_developer_assistant\Relationship\PhpDependencyResolver;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests PHP constructor dependency matching without booting Drupal.
 */
#[Group('drupal_developer_assistant')]
final class PhpDependencyResolverTest extends UnitTestCase {

  /**
   * Tests exact IDs, unique classes, nullable types, and safe omissions.
   */
  public function testResolvesUnambiguousConstructorDependencies(): void {
    $source_file = new ModuleSourceFileComponent(
      moduleId: 'example',
      relativePath: 'src/ExampleConsumer.php',
      fileType: 'PHP',
      category: 'Source code',
      size: 100,
      sourcePath: 'modules/custom/example/src/ExampleConsumer.php',
    );
    $parameters = [
      $this->parameter('manager', 'Drupal\\example\\ExampleManagerInterface'),
      $this->parameter('helper', 'Drupal\\example\\UniqueHelper'),
      $this->parameter('optional', '?Drupal\\example\\OptionalInterface'),
      $this->parameter('shared', 'Drupal\\example\\SharedDependency'),
      $this->parameter(
        'choice',
        'Drupal\\example\\First|Drupal\\example\\Second',
      ),
      $this->parameter('label', 'string'),
      $this->parameter('untyped', NULL),
    ];
    $constructor = new PhpMethod(
      name: '__construct',
      visibility: 'public',
      static: FALSE,
      abstract: FALSE,
      final: FALSE,
      returnsByReference: FALSE,
      returnType: NULL,
      parameters: $parameters,
      attributes: [],
    );
    $analysis = new PhpFileAnalysis(
      sourceFile: $source_file,
      namespaces: ['Drupal\\example'],
      imports: [],
      symbols: [
        new PhpSymbol(
          kind: 'class',
          name: 'ExampleConsumer',
          fullyQualifiedName: 'Drupal\\example\\ExampleConsumer',
          modifiers: ['final'],
          extends: [],
          implements: [],
          traits: [],
          attributes: [],
          properties: [],
          methods: [$constructor],
        ),
        new PhpSymbol(
          kind: 'interface',
          name: 'IgnoredInterface',
          fullyQualifiedName: 'Drupal\\example\\IgnoredInterface',
          modifiers: [],
          extends: [],
          implements: [],
          traits: [],
          attributes: [],
          properties: [],
          methods: [$constructor],
        ),
      ],
      functions: [],
      error: NULL,
    );
    $services = [
      $this->service(
        'Drupal\\example\\ExampleManagerInterface',
        'Drupal\\example\\ExampleManager',
        'example.manager',
      ),
      $this->service(
        'example.manager',
        'Drupal\\example\\ExampleManager',
      ),
      $this->service(
        'example.helper',
        'Drupal\\example\\UniqueHelper',
      ),
      $this->service(
        'Drupal\\example\\OptionalInterface',
        'Drupal\\example\\OptionalService',
        'example.optional',
      ),
      $this->service(
        'example.optional',
        'Drupal\\example\\OptionalService',
      ),
      $this->service(
        'example.shared.first',
        'Drupal\\example\\SharedDependency',
      ),
      $this->service(
        'example.shared.second',
        'Drupal\\example\\SharedDependency',
      ),
    ];

    $resolver = new PhpDependencyResolver();

    $this->assertEquals([
      $this->relationship(
        'Drupal\\example\\ExampleManagerInterface',
        '$manager',
        'Drupal\\example\\ExampleManagerInterface',
        'service_id',
      ),
      $this->relationship(
        'example.helper',
        '$helper',
        'Drupal\\example\\UniqueHelper',
        'service_class',
      ),
      $this->relationship(
        'Drupal\\example\\OptionalInterface',
        '$optional',
        '?Drupal\\example\\OptionalInterface',
        'service_id',
      ),
    ], $resolver->resolve([$analysis], $services));
  }

  /**
   * Creates a PHP parameter test record.
   */
  private function parameter(string $name, ?string $type): PhpParameter {
    return new PhpParameter(
      name: $name,
      type: $type,
      byReference: FALSE,
      variadic: FALSE,
      promoted: TRUE,
      hasDefault: FALSE,
      attributes: [],
    );
  }

  /**
   * Creates a service test record.
   */
  private function service(
    string $id,
    ?string $class_name,
    ?string $alias_target = NULL,
  ): ServiceComponent {
    return new ServiceComponent(
      id: $id,
      className: $class_name,
      aliasTarget: $alias_target,
      references: [],
    );
  }

  /**
   * Creates an expected dependency relationship.
   */
  private function relationship(
    string $service_id,
    string $parameter,
    string $declared_type,
    string $match,
  ): ComponentRelationship {
    return new ComponentRelationship(
      sourceType: 'class',
      sourceId: 'Drupal\\example\\ExampleConsumer',
      relationship: 'depends_on',
      targetType: 'service',
      targetId: $service_id,
      metadata: [
        'context' => 'constructor',
        'parameter' => $parameter,
        'declared_type' => $declared_type,
        'match' => $match,
        'source_path' => 'modules/custom/example/src/ExampleConsumer.php',
      ],
    );
  }

}
