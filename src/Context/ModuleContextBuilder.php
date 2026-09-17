<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Context;

use Drupal\drupal_developer_assistant\Model\ComponentRelationship;
use Drupal\drupal_developer_assistant\Model\ModuleArchitecture;
use Drupal\drupal_developer_assistant\Model\ModuleArchitectureComponent;
use Drupal\drupal_developer_assistant\Model\ModuleSourceFileComponent;
use Drupal\drupal_developer_assistant\Model\PhpFileAnalysis;
use Drupal\drupal_developer_assistant\Model\PhpFunction;
use Drupal\drupal_developer_assistant\Model\PhpMethod;
use Drupal\drupal_developer_assistant\Model\PhpParameter;
use Drupal\drupal_developer_assistant\Model\PhpProperty;
use Drupal\drupal_developer_assistant\Model\PhpSymbol;

/**
 * Normalizes module architecture objects into a portable context document.
 */
final class ModuleContextBuilder implements ModuleContextBuilderInterface {

  /**
   * Number of strings shortened during the current build.
   */
  private int $shortenedStrings = 0;

  /**
   * Constructs a module context builder.
   */
  public function __construct(
    private readonly ModuleContextLimits $limits,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function build(ModuleArchitecture $architecture): ModuleContext {
    $this->shortenedStrings = 0;
    $selected_components = array_slice(
      $architecture->components,
      0,
      $this->limits->maxArchitectureComponents,
    );
    $selected_source_files = array_slice(
      $architecture->sourceFiles,
      0,
      $this->limits->maxSourceFiles,
    );
    $selected_php_files = $this->selectPhpFiles($architecture->phpFiles);
    $selected_relationships = array_slice(
      $architecture->relationships,
      0,
      $this->limits->maxRelationships,
    );
    $components = array_map(
      $this->component(...),
      $selected_components,
    );
    $components = [
      ...$components,
      ...array_map($this->sourceFile(...), $selected_source_files),
    ];
    $php_structure = array_map(
      $this->phpFile(...),
      $selected_php_files,
    );
    $available = $this->counts(
      $architecture->components,
      $architecture->sourceFiles,
      $architecture->phpFiles,
      $architecture->relationships,
      FALSE,
    );
    $included = $this->counts(
      $selected_components,
      $selected_source_files,
      $selected_php_files,
      $selected_relationships,
      TRUE,
    );
    $omitted = [];
    foreach ($available as $name => $count) {
      $omitted[$name] = $count - $included[$name];
    }
    $module = [
      'id' => $this->text($architecture->module->id),
      'name' => $this->text($architecture->module->label),
      'path' => $this->nullableText($architecture->module->sourcePath),
      'package' => $this->nullableText($architecture->module->package),
      'version' => $this->nullableText($architecture->module->version),
      'dependencies' => $this->stringList(
        $architecture->module->dependencies,
      ),
    ];
    $relationships = array_map(
      $this->relationship(...),
      $selected_relationships,
    );

    return new ModuleContext(
      module: $module,
      summary: [
        'available' => $available,
        'included' => $included,
        'omitted' => $omitted,
        'limits' => [
          'architecture_components' => $this->limits->maxArchitectureComponents,
          'source_files' => $this->limits->maxSourceFiles,
          'php_files' => $this->limits->maxPhpFiles,
          'symbols_per_file' => $this->limits->maxSymbolsPerFile,
          'properties_per_symbol' => $this->limits->maxPropertiesPerSymbol,
          'methods_per_symbol' => $this->limits->maxMethodsPerSymbol,
          'functions_per_file' => $this->limits->maxFunctionsPerFile,
          'parameters_per_callable' => $this->limits->maxParametersPerCallable,
          'relationships' => $this->limits->maxRelationships,
          'string_bytes' => $this->limits->maxStringBytes,
        ],
        'strings_shortened' => $this->shortenedStrings,
        'truncated' => array_sum($omitted) > 0
        || $this->shortenedStrings > 0,
      ],
      components: $components,
      phpStructure: $php_structure,
      relationships: $relationships,
    );
  }

  /**
   * Selects PHP files deterministically, prioritizing production code.
   *
   * @param list<\Drupal\drupal_developer_assistant\Model\PhpFileAnalysis> $files
   *   Available PHP analyses.
   *
   * @return list<\Drupal\drupal_developer_assistant\Model\PhpFileAnalysis>
   *   PHP analyses included in the context.
   */
  private function selectPhpFiles(array $files): array {
    usort(
      $files,
      static fn(PhpFileAnalysis $first, PhpFileAnalysis $second): int => [
        $first->sourceFile->category === 'Test' ? 1 : 0,
        $first->sourceFile->relativePath,
      ] <=> [
        $second->sourceFile->category === 'Test' ? 1 : 0,
        $second->sourceFile->relativePath,
      ],
    );

    return array_slice($files, 0, $this->limits->maxPhpFiles);
  }

  /**
   * Counts available or limit-adjusted records at every bounded level.
   *
   * @param list<\Drupal\drupal_developer_assistant\Model\ModuleArchitectureComponent> $components
   *   Architecture components to count.
   * @param list<\Drupal\drupal_developer_assistant\Model\ModuleSourceFileComponent> $source_files
   *   Source files to count.
   * @param list<\Drupal\drupal_developer_assistant\Model\PhpFileAnalysis> $php_files
   *   PHP analyses to count.
   * @param list<\Drupal\drupal_developer_assistant\Model\ComponentRelationship> $relationships
   *   Relationships to count.
   * @param bool $apply_nested_limits
   *   Whether per-file and per-symbol limits should be applied.
   *
   * @return array<string, int>
   *   Counts keyed by context record type.
   */
  private function counts(
    array $components,
    array $source_files,
    array $php_files,
    array $relationships,
    bool $apply_nested_limits,
  ): array {
    $counts = [
      'architecture_components' => count($components),
      'source_files' => count($source_files),
      'php_files' => count($php_files),
      'php_symbols' => 0,
      'php_properties' => 0,
      'php_methods' => 0,
      'php_functions' => 0,
      'php_parameters' => 0,
      'relationships' => count($relationships),
    ];
    foreach ($php_files as $file) {
      $symbols = $apply_nested_limits
        ? array_slice($file->symbols, 0, $this->limits->maxSymbolsPerFile)
        : $file->symbols;
      $functions = $apply_nested_limits
        ? array_slice($file->functions, 0, $this->limits->maxFunctionsPerFile)
        : $file->functions;
      $counts['php_symbols'] += count($symbols);
      $counts['php_functions'] += count($functions);
      foreach ($symbols as $symbol) {
        $properties = $apply_nested_limits
          ? array_slice(
            $symbol->properties,
            0,
            $this->limits->maxPropertiesPerSymbol,
          )
          : $symbol->properties;
        $methods = $apply_nested_limits
          ? array_slice(
            $symbol->methods,
            0,
            $this->limits->maxMethodsPerSymbol,
          )
          : $symbol->methods;
        $counts['php_properties'] += count($properties);
        $counts['php_methods'] += count($methods);
        foreach ($methods as $method) {
          $counts['php_parameters'] += $apply_nested_limits
            ? min(
              count($method->parameters),
              $this->limits->maxParametersPerCallable,
            )
            : count($method->parameters);
        }
      }
      foreach ($functions as $function) {
        $counts['php_parameters'] += $apply_nested_limits
          ? min(
            count($function->parameters),
            $this->limits->maxParametersPerCallable,
          )
          : count($function->parameters);
      }
    }

    return $counts;
  }

  /**
   * Normalizes a discovered architecture component.
   *
   * @return array<string, mixed>
   *   Primitive component facts.
   */
  private function component(ModuleArchitectureComponent $component): array {
    return [
      'type' => $this->text($component->type),
      'id' => $this->text($component->id),
      'label' => $this->text($component->label),
      'source_path' => $this->nullableText($component->sourcePath),
    ];
  }

  /**
   * Normalizes a discovered source-file component without reading its content.
   *
   * @return array<string, mixed>
   *   Primitive file metadata.
   */
  private function sourceFile(ModuleSourceFileComponent $file): array {
    return [
      'type' => $this->text($file->type),
      'id' => $this->text($file->id),
      'label' => $this->text($file->label),
      'source_path' => $this->nullableText($file->sourcePath),
      'module_id' => $this->text($file->moduleId),
      'relative_path' => $this->text($file->relativePath),
      'file_type' => $this->text($file->fileType),
      'category' => $this->text($file->category),
      'size_bytes' => $file->size,
    ];
  }

  /**
   * Normalizes one parsed PHP file.
   *
   * @return array<string, mixed>
   *   Primitive PHP structural facts.
   */
  private function phpFile(PhpFileAnalysis $file): array {
    $imports = [];
    foreach ($file->imports as $alias => $name) {
      $imports[$this->text($alias)] = $this->text($name);
    }

    return [
      'path' => $this->text($file->sourceFile->relativePath),
      'namespaces' => $this->stringList($file->namespaces),
      'imports' => $imports,
      'symbols' => array_map(
        $this->symbol(...),
        array_slice(
          $file->symbols,
          0,
          $this->limits->maxSymbolsPerFile,
        ),
      ),
      'functions' => array_map(
        $this->phpFunction(...),
        array_slice(
          $file->functions,
          0,
          $this->limits->maxFunctionsPerFile,
        ),
      ),
      'analysis_error' => $this->nullableText($file->error),
    ];
  }

  /**
   * Normalizes a PHP class, interface, trait, or enum.
   *
   * @return array<string, mixed>
   *   Primitive symbol facts.
   */
  private function symbol(PhpSymbol $symbol): array {
    return [
      'kind' => $this->text($symbol->kind),
      'name' => $this->text($symbol->name),
      'fully_qualified_name' => $this->text($symbol->fullyQualifiedName),
      'modifiers' => $this->stringList($symbol->modifiers),
      'extends' => $this->stringList($symbol->extends),
      'implements' => $this->stringList($symbol->implements),
      'traits' => $this->stringList($symbol->traits),
      'attributes' => $this->stringList($symbol->attributes),
      'start_line' => $symbol->startLine,
      'end_line' => $symbol->endLine,
      'properties' => array_map(
        $this->property(...),
        array_slice(
          $symbol->properties,
          0,
          $this->limits->maxPropertiesPerSymbol,
        ),
      ),
      'methods' => array_map(
        $this->method(...),
        array_slice(
          $symbol->methods,
          0,
          $this->limits->maxMethodsPerSymbol,
        ),
      ),
    ];
  }

  /**
   * Normalizes a PHP property declaration.
   *
   * @return array<string, mixed>
   *   Primitive property facts.
   */
  private function property(PhpProperty $property): array {
    return [
      'name' => $this->text($property->name),
      'visibility' => $this->text($property->visibility),
      'type' => $this->nullableText($property->type),
      'static' => $property->static,
      'readonly' => $property->readonly,
      'attributes' => $this->stringList($property->attributes),
    ];
  }

  /**
   * Normalizes a PHP method declaration.
   *
   * @return array<string, mixed>
   *   Primitive method facts.
   */
  private function method(PhpMethod $method): array {
    return [
      'name' => $this->text($method->name),
      'visibility' => $this->text($method->visibility),
      'static' => $method->static,
      'abstract' => $method->abstract,
      'final' => $method->final,
      'returns_by_reference' => $method->returnsByReference,
      'return_type' => $this->nullableText($method->returnType),
      'start_line' => $method->startLine,
      'end_line' => $method->endLine,
      'parameters' => array_map(
        $this->parameter(...),
        array_slice(
          $method->parameters,
          0,
          $this->limits->maxParametersPerCallable,
        ),
      ),
      'attributes' => $this->stringList($method->attributes),
    ];
  }

  /**
   * Normalizes a PHP function declaration.
   *
   * @return array<string, mixed>
   *   Primitive function facts.
   */
  private function phpFunction(PhpFunction $function): array {
    return [
      'name' => $this->text($function->name),
      'fully_qualified_name' => $this->text($function->fullyQualifiedName),
      'returns_by_reference' => $function->returnsByReference,
      'return_type' => $this->nullableText($function->returnType),
      'start_line' => $function->startLine,
      'end_line' => $function->endLine,
      'parameters' => array_map(
        $this->parameter(...),
        array_slice(
          $function->parameters,
          0,
          $this->limits->maxParametersPerCallable,
        ),
      ),
      'attributes' => $this->stringList($function->attributes),
    ];
  }

  /**
   * Normalizes a PHP method or function parameter declaration.
   *
   * @return array<string, mixed>
   *   Primitive parameter facts without its default value.
   */
  private function parameter(PhpParameter $parameter): array {
    return [
      'name' => $this->text($parameter->name),
      'type' => $this->nullableText($parameter->type),
      'by_reference' => $parameter->byReference,
      'variadic' => $parameter->variadic,
      'promoted' => $parameter->promoted,
      'has_default' => $parameter->hasDefault,
      'attributes' => $this->stringList($parameter->attributes),
    ];
  }

  /**
   * Normalizes an architectural relationship.
   *
   * @return array<string, mixed>
   *   Primitive source, target, relationship, and metadata facts.
   */
  private function relationship(ComponentRelationship $relationship): array {
    $metadata = [];
    foreach ($relationship->metadata as $name => $value) {
      $metadata[$this->text($name)] = $this->text($value);
    }

    return [
      'source' => [
        'type' => $this->text($relationship->sourceType),
        'id' => $this->text($relationship->sourceId),
      ],
      'relationship' => $this->text($relationship->relationship),
      'target' => [
        'type' => $this->text($relationship->targetType),
        'id' => $this->text($relationship->targetId),
      ],
      'metadata' => $metadata,
    ];
  }

  /**
   * Normalizes a list of strings to the configured per-string byte limit.
   *
   * @param list<string> $values
   *   String values to normalize.
   *
   * @return list<string>
   *   Byte-limited strings.
   */
  private function stringList(array $values): array {
    return array_map($this->text(...), $values);
  }

  /**
   * Normalizes an optional string to the configured byte limit.
   */
  private function nullableText(?string $value): ?string {
    return $value === NULL ? NULL : $this->text($value);
  }

  /**
   * Shortens a string safely without splitting a multibyte character.
   */
  private function text(string $value): string {
    if (strlen($value) <= $this->limits->maxStringBytes) {
      return $value;
    }

    $this->shortenedStrings++;

    return mb_strcut(
      $value,
      0,
      $this->limits->maxStringBytes - 3,
      'UTF-8',
    ) . '...';
  }

}
