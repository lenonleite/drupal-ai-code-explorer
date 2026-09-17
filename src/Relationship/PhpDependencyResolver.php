<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Relationship;

use Drupal\drupal_developer_assistant\Model\ComponentRelationship;
use Drupal\drupal_developer_assistant\Model\PhpFileAnalysis;
use Drupal\drupal_developer_assistant\Model\PhpMethod;
use Drupal\drupal_developer_assistant\Model\ServiceComponent;

/**
 * Matches analyzed constructor types to unambiguous Drupal services.
 */
final class PhpDependencyResolver implements PhpDependencyResolverInterface {

  /**
   * PHP built-in types that cannot identify a Drupal service.
   *
   * @var list<string>
   */
  private const BUILT_IN_TYPES = [
    'array',
    'bool',
    'callable',
    'false',
    'float',
    'int',
    'iterable',
    'mixed',
    'never',
    'null',
    'object',
    'parent',
    'self',
    'static',
    'string',
    'true',
    'void',
  ];

  /**
   * {@inheritdoc}
   */
  public function resolve(array $php_files, array $services): array {
    [$services_by_id, $service_ids_by_class] = $this->serviceIndexes(
      $services,
    );
    $relationships = [];

    foreach ($php_files as $php_file) {
      if (!$php_file instanceof PhpFileAnalysis) {
        throw new \UnexpectedValueException('PHP analyses must be PhpFileAnalysis objects.');
      }
      if ($php_file->error !== NULL) {
        continue;
      }

      foreach ($php_file->symbols as $symbol) {
        if ($symbol->kind !== 'class') {
          continue;
        }

        $constructor = $this->constructor($symbol->methods);
        if ($constructor === NULL) {
          continue;
        }

        foreach ($constructor->parameters as $parameter) {
          $dependency_type = $this->dependencyType($parameter->type);
          if ($dependency_type === NULL) {
            continue;
          }

          $match = $this->matchService(
            $dependency_type,
            $services_by_id,
            $service_ids_by_class,
          );
          if ($match === NULL) {
            continue;
          }

          [$service_id, $match_type] = $match;
          $relationships[] = new ComponentRelationship(
            sourceType: 'class',
            sourceId: $symbol->fullyQualifiedName,
            relationship: 'depends_on',
            targetType: 'service',
            targetId: $service_id,
            metadata: [
              'context' => 'constructor',
              'parameter' => '$' . $parameter->name,
              'declared_type' => $parameter->type ?? '',
              'match' => $match_type,
              'source_path' => $php_file->sourceFile->sourcePath ?? '',
            ],
          );
        }
      }
    }

    return $relationships;
  }

  /**
   * Builds service lookup tables by identifier and implementation class.
   *
   * @param list<\Drupal\drupal_developer_assistant\Model\ServiceComponent> $services
   *   Collected Drupal services.
   *
   * @return array{0: array<string, string>, 1: array<string, list<string>>}
   *   Normalized service IDs and implementation-class service IDs.
   */
  private function serviceIndexes(array $services): array {
    $services_by_id = [];
    $service_ids_by_class = [];
    foreach ($services as $service) {
      if (!$service instanceof ServiceComponent) {
        throw new \UnexpectedValueException('Services must be ServiceComponent objects.');
      }

      $normalized_id = $this->normalizeClassName($service->id);
      $services_by_id[$normalized_id] = $service->id;
      if ($service->aliasTarget !== NULL || $service->className === NULL) {
        continue;
      }

      $class_name = strtolower($this->normalizeClassName(
        $service->className,
      ));
      $service_ids_by_class[$class_name][] = $service->id;
    }

    return [$services_by_id, $service_ids_by_class];
  }

  /**
   * Finds a constructor declared directly by a class.
   *
   * @param list<\Drupal\drupal_developer_assistant\Model\PhpMethod> $methods
   *   Methods declared directly by the class.
   */
  private function constructor(array $methods): ?PhpMethod {
    foreach ($methods as $method) {
      if ($method->name === '__construct') {
        return $method;
      }
    }

    return NULL;
  }

  /**
   * Returns one safe service-class candidate from a PHP type declaration.
   */
  private function dependencyType(?string $declared_type): ?string {
    if ($declared_type === NULL || $declared_type === '') {
      return NULL;
    }

    $types = array_values(array_filter(
      preg_split('/[|&]/', $declared_type) ?: [],
      static fn(string $type): bool => strtolower(ltrim($type, '?')) !== 'null',
    ));
    if (count($types) !== 1) {
      return NULL;
    }

    $type = $this->normalizeClassName(ltrim($types[0], '?'));
    if (in_array(strtolower($type), self::BUILT_IN_TYPES, TRUE)) {
      return NULL;
    }

    return $type;
  }

  /**
   * Matches a declared class type to one unambiguous Drupal service.
   *
   * @param string $dependency_type
   *   The normalized constructor parameter class or interface type.
   * @param array<string, string> $services_by_id
   *   Service IDs keyed by normalized ID.
   * @param array<string, list<string>> $service_ids_by_class
   *   Non-alias service IDs keyed by normalized implementation class.
   *
   * @return array{0: string, 1: string}|null
   *   The matched service ID and matching strategy.
   */
  private function matchService(
    string $dependency_type,
    array $services_by_id,
    array $service_ids_by_class,
  ): ?array {
    if (isset($services_by_id[$dependency_type])) {
      return [$services_by_id[$dependency_type], 'service_id'];
    }

    $class_matches = $service_ids_by_class[strtolower($dependency_type)] ?? [];
    if (count($class_matches) !== 1) {
      return NULL;
    }

    return [$class_matches[0], 'service_class'];
  }

  /**
   * Normalizes a PHP class name for comparison with Drupal service metadata.
   */
  private function normalizeClassName(string $class_name): string {
    return ltrim($class_name, '\\');
  }

}
