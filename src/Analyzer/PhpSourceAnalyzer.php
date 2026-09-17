<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Analyzer;

use Drupal\drupal_developer_assistant\Model\ModuleComponent;
use Drupal\drupal_developer_assistant\Model\ModuleSourceFileComponent;
use Drupal\drupal_developer_assistant\Model\PhpFileAnalysis;
use Drupal\drupal_developer_assistant\Model\PhpFunction;
use Drupal\drupal_developer_assistant\Model\PhpMethod;
use Drupal\drupal_developer_assistant\Model\PhpParameter;
use Drupal\drupal_developer_assistant\Model\PhpProperty;
use Drupal\drupal_developer_assistant\Model\PhpSymbol;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UnionType;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Parses PHP structure without loading or executing inspected source files.
 */
final class PhpSourceAnalyzer implements PhpSourceAnalyzerInterface {

  /**
   * Maximum PHP source size accepted for structural analysis.
   */
  private const int MAX_FILE_SIZE = 1_048_576;

  /**
   * The static PHP parser.
   */
  private readonly Parser $parser;

  /**
   * Constructs a PHP source analyzer.
   */
  public function __construct(
    private readonly string $appRoot,
  ) {
    $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
  }

  /**
   * {@inheritdoc}
   */
  public function analyze(
    ModuleComponent $module,
    ModuleSourceFileComponent $source_file,
  ): PhpFileAnalysis {
    if ($source_file->moduleId !== $module->id) {
      return $this->failure($source_file, 'The source file belongs to a different module.');
    }
    if ($source_file->fileType !== 'PHP') {
      return $this->failure($source_file, 'Only PHP source files can be analyzed.');
    }

    $app_root = realpath($this->appRoot);
    $module_root = $module->sourcePath === NULL
      ? FALSE
      : realpath($this->appRoot . DIRECTORY_SEPARATOR . $module->sourcePath);
    $source_path = $this->appRoot
      . DIRECTORY_SEPARATOR
      . $source_file->sourcePath;
    $file_path = realpath($source_path);
    $expected_path = $module_root === FALSE
      ? FALSE
      : realpath($module_root . DIRECTORY_SEPARATOR . $source_file->relativePath);
    if (
      $app_root === FALSE
      || $module_root === FALSE
      || $module_root === $app_root
      || !$this->isInside($module_root, $app_root)
      || $file_path === FALSE
      || $expected_path === FALSE
      || $file_path !== $expected_path
      || !$this->isInside($file_path, $module_root)
      || !is_file($file_path)
      || is_link($source_path)
    ) {
      return $this->failure($source_file, 'The PHP source path is invalid or outside the module directory.');
    }

    $size = filesize($file_path);
    if ($size === FALSE || $size > self::MAX_FILE_SIZE) {
      return $this->failure($source_file, 'The PHP source file exceeds the analysis size limit.');
    }
    $code = file_get_contents($file_path);
    if ($code === FALSE) {
      return $this->failure($source_file, 'The PHP source file could not be read.');
    }

    try {
      $nodes = $this->parser->parse($code) ?? [];
      $namespaces = $this->namespaces($nodes);
      $imports = $this->imports($nodes);
      $traverser = new NodeTraverser();
      $traverser->addVisitor(new NameResolver());
      $nodes = $traverser->traverse($nodes);

      return new PhpFileAnalysis(
        sourceFile: $source_file,
        namespaces: $namespaces,
        imports: $imports,
        symbols: $this->symbols($nodes),
        functions: $this->functions($nodes),
        error: NULL,
      );
    }
    catch (Error $error) {
      return $this->failure(
        $source_file,
        sprintf(
          'PHP parsing failed on line %d: %s',
          $error->getStartLine(),
          $error->getRawMessage(),
        ),
      );
    }
  }

  /**
   * Extracts declared namespace names before name resolution.
   *
   * @param list<\PhpParser\Node\Stmt> $nodes
   *   Parsed PHP statements.
   *
   * @return list<string>
   *   Unique namespace names in declaration order.
   */
  private function namespaces(array $nodes): array {
    $namespaces = [];
    $finder = new NodeFinder();
    foreach ($finder->findInstanceOf($nodes, Namespace_::class) as $namespace) {
      if ($namespace->name !== NULL) {
        $namespaces[] = $namespace->name->toString();
      }
    }

    return array_values(array_unique($namespaces));
  }

  /**
   * Extracts class, function, and constant import declarations.
   *
   * @param list<\PhpParser\Node\Stmt> $nodes
   *   Parsed PHP statements.
   *
   * @return array<string, string>
   *   Imported names keyed by a display alias.
   */
  private function imports(array $nodes): array {
    $imports = [];
    $finder = new NodeFinder();
    foreach ($finder->findInstanceOf($nodes, Use_::class) as $use_statement) {
      foreach ($use_statement->uses as $use) {
        $type = $use->type === Use_::TYPE_UNKNOWN
          ? $use_statement->type
          : $use->type;
        $imports[$this->importAlias($use->getAlias()->toString(), $type)]
          = $use->name->toString();
      }
    }
    foreach ($finder->findInstanceOf($nodes, GroupUse::class) as $use_statement) {
      foreach ($use_statement->uses as $use) {
        $type = $use->type === Use_::TYPE_UNKNOWN
          ? $use_statement->type
          : $use->type;
        $imports[$this->importAlias($use->getAlias()->toString(), $type)]
          = Name::concat($use_statement->prefix, $use->name)->toString();
      }
    }
    ksort($imports, SORT_STRING);

    return $imports;
  }

  /**
   * Distinguishes function and constant aliases from class aliases.
   */
  private function importAlias(string $alias, int $type): string {
    return match ($type) {
      Use_::TYPE_FUNCTION => 'function ' . $alias,
      Use_::TYPE_CONSTANT => 'const ' . $alias,
      default => $alias,
    };
  }

  /**
   * Extracts named class-like declarations.
   *
   * @param list<\PhpParser\Node\Stmt> $nodes
   *   Name-resolved PHP statements.
   *
   * @return list<\Drupal\drupal_developer_assistant\Model\PhpSymbol>
   *   Structural class-like symbol metadata.
   */
  private function symbols(array $nodes): array {
    $symbols = [];
    $finder = new NodeFinder();
    foreach ($finder->findInstanceOf($nodes, ClassLike::class) as $node) {
      if ($node->name === NULL) {
        continue;
      }

      $extends = [];
      if ($node instanceof Class_ && $node->extends !== NULL) {
        $extends[] = $node->extends->toString();
      }
      elseif ($node instanceof Interface_) {
        $extends = array_map(
          static fn(Name $name): string => $name->toString(),
          $node->extends,
        );
      }

      $implements = $node instanceof Class_ || $node instanceof Enum_
        ? array_map(
          static fn(Name $name): string => $name->toString(),
          $node->implements,
        )
        : [];
      $traits = [];
      foreach ($node->getTraitUses() as $trait_use) {
        array_push(
          $traits,
          ...array_map(
            static fn(Name $name): string => $name->toString(),
            $trait_use->traits,
          ),
        );
      }

      $symbols[] = new PhpSymbol(
        kind: $this->symbolKind($node),
        name: $node->name->toString(),
        fullyQualifiedName: $node->namespacedName?->toString()
          ?? $node->name->toString(),
        modifiers: $this->symbolModifiers($node),
        extends: $extends,
        implements: $implements,
        traits: $traits,
        attributes: $this->attributes($node->attrGroups),
        properties: $this->properties($node),
        methods: $this->methods($node),
        startLine: $node->getStartLine(),
        endLine: $node->getEndLine(),
      );
    }

    return $symbols;
  }

  /**
   * Returns the normalized kind for a class-like syntax node.
   */
  private function symbolKind(ClassLike $node): string {
    return match (TRUE) {
      $node instanceof Class_ => 'class',
      $node instanceof Interface_ => 'interface',
      $node instanceof Trait_ => 'trait',
      $node instanceof Enum_ => 'enum',
      default => 'class_like',
    };
  }

  /**
   * Extracts explicit class modifiers.
   *
   * @return list<string>
   *   Modifier names in declaration order.
   */
  private function symbolModifiers(ClassLike $node): array {
    if (!$node instanceof Class_) {
      return [];
    }

    $modifiers = [];
    if ($node->isAbstract()) {
      $modifiers[] = 'abstract';
    }
    if ($node->isFinal()) {
      $modifiers[] = 'final';
    }
    if ($node->isReadonly()) {
      $modifiers[] = 'readonly';
    }

    return $modifiers;
  }

  /**
   * Extracts properties declared directly by a class-like symbol.
   *
   * @return list<\Drupal\drupal_developer_assistant\Model\PhpProperty>
   *   Property metadata in declaration order.
   */
  private function properties(ClassLike $node): array {
    $properties = [];
    foreach ($node->getProperties() as $property) {
      foreach ($property->props as $property_item) {
        $properties[] = new PhpProperty(
          name: $property_item->name->toString(),
          visibility: $this->visibility($property),
          type: $this->typeName($property->type),
          static: $property->isStatic(),
          readonly: $property->isReadonly(),
          attributes: $this->attributes($property->attrGroups),
        );
      }
    }

    return $properties;
  }

  /**
   * Extracts methods declared directly by a class-like symbol.
   *
   * @return list<\Drupal\drupal_developer_assistant\Model\PhpMethod>
   *   Method metadata in declaration order.
   */
  private function methods(ClassLike $node): array {
    return array_map(
      fn(ClassMethod $method): PhpMethod => new PhpMethod(
        name: $method->name->toString(),
        visibility: $this->visibility($method),
        static: $method->isStatic(),
        abstract: $method->isAbstract(),
        final: $method->isFinal(),
        returnsByReference: $method->returnsByRef(),
        returnType: $this->typeName($method->returnType),
        parameters: $this->parameters($method->params),
        attributes: $this->attributes($method->attrGroups),
        startLine: $method->getStartLine(),
        endLine: $method->getEndLine(),
      ),
      $node->getMethods(),
    );
  }

  /**
   * Extracts named functions.
   *
   * @param list<\PhpParser\Node\Stmt> $nodes
   *   Name-resolved PHP statements.
   *
   * @return list<\Drupal\drupal_developer_assistant\Model\PhpFunction>
   *   Function metadata in declaration order.
   */
  private function functions(array $nodes): array {
    $finder = new NodeFinder();

    return array_map(
      fn(Function_ $function): PhpFunction => new PhpFunction(
        name: $function->name->toString(),
        fullyQualifiedName: $function->namespacedName->toString(),
        returnsByReference: $function->returnsByRef(),
        returnType: $this->typeName($function->returnType),
        parameters: $this->parameters($function->params),
        attributes: $this->attributes($function->attrGroups),
        startLine: $function->getStartLine(),
        endLine: $function->getEndLine(),
      ),
      $finder->findInstanceOf($nodes, Function_::class),
    );
  }

  /**
   * Extracts typed parameter metadata without retaining default values.
   *
   * @param list<\PhpParser\Node\Param> $parameters
   *   Parsed parameter nodes.
   *
   * @return list<\Drupal\drupal_developer_assistant\Model\PhpParameter>
   *   Parameter metadata in declaration order.
   */
  private function parameters(array $parameters): array {
    $records = [];
    foreach ($parameters as $parameter) {
      $records[] = new PhpParameter(
        name: $parameter->var instanceof Variable
          && is_string($parameter->var->name)
            ? $parameter->var->name
            : 'unknown',
        type: $this->typeName($parameter->type),
        byReference: $parameter->byRef,
        variadic: $parameter->variadic,
        promoted: $parameter->isPromoted(),
        hasDefault: $parameter->default !== NULL,
        attributes: $this->attributes($parameter->attrGroups),
      );
    }

    return $records;
  }

  /**
   * Normalizes a PHP type syntax node to a readable string.
   */
  private function typeName(?Node $type): ?string {
    return match (TRUE) {
      $type instanceof Identifier => $type->toString(),
      $type instanceof Name => $type->toString(),
      $type instanceof NullableType => '?' . $this->typeName($type->type),
      $type instanceof UnionType => implode('|', array_map(
        $this->typeName(...),
        $type->types,
      )),
      $type instanceof IntersectionType => implode('&', array_map(
        $this->typeName(...),
        $type->types,
      )),
      default => NULL,
    };
  }

  /**
   * Extracts fully resolved attribute class names.
   *
   * @param list<\PhpParser\Node\AttributeGroup> $attribute_groups
   *   Parsed PHP attribute groups.
   *
   * @return list<string>
   *   Attribute class names in declaration order.
   */
  private function attributes(array $attribute_groups): array {
    $attributes = [];
    foreach ($attribute_groups as $attribute_group) {
      foreach ($attribute_group->attrs as $attribute) {
        $attributes[] = $attribute->name->toString();
      }
    }

    return $attributes;
  }

  /**
   * Returns the declared visibility for a property or method.
   */
  private function visibility(Property|ClassMethod $node): string {
    return match (TRUE) {
      $node->isPrivate() => 'private',
      $node->isProtected() => 'protected',
      default => 'public',
    };
  }

  /**
   * Builds a controlled unsuccessful analysis result.
   */
  private function failure(
    ModuleSourceFileComponent $source_file,
    string $error,
  ): PhpFileAnalysis {
    return new PhpFileAnalysis(
      sourceFile: $source_file,
      namespaces: [],
      imports: [],
      symbols: [],
      functions: [],
      error: $error,
    );
  }

  /**
   * Determines whether a canonical path is inside a canonical directory.
   */
  private function isInside(string $path, string $directory): bool {
    return $path === $directory
      || str_starts_with($path, $directory . DIRECTORY_SEPARATOR);
  }

}
