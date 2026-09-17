<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Architecture;

use Drupal\drupal_developer_assistant\Analyzer\PhpSourceAnalyzerInterface;
use Drupal\drupal_developer_assistant\Collector\CollectorManagerInterface;
use Drupal\drupal_developer_assistant\Collector\ModuleSourceFileCollectorInterface;
use Drupal\drupal_developer_assistant\Model\Component;
use Drupal\drupal_developer_assistant\Model\ComponentRelationship;
use Drupal\drupal_developer_assistant\Model\ConfigurationComponent;
use Drupal\drupal_developer_assistant\Model\EntityTypeComponent;
use Drupal\drupal_developer_assistant\Model\HookComponent;
use Drupal\drupal_developer_assistant\Model\ModuleArchitecture;
use Drupal\drupal_developer_assistant\Model\ModuleArchitectureComponent;
use Drupal\drupal_developer_assistant\Model\ModuleComponent;
use Drupal\drupal_developer_assistant\Model\NavigationComponent;
use Drupal\drupal_developer_assistant\Model\PluginComponent;
use Drupal\drupal_developer_assistant\Model\PluginManagerComponent;
use Drupal\drupal_developer_assistant\Model\RouteComponent;
use Drupal\drupal_developer_assistant\Model\ServiceComponent;
use Drupal\drupal_developer_assistant\Relationship\PhpDependencyResolverInterface;
use Drupal\drupal_developer_assistant\Relationship\RelationshipManagerInterface;
use Drupal\drupal_developer_assistant\Resolver\SourcePathResolverInterface;

/**
 * Builds module-scoped architecture reports from collected components.
 */
final class ModuleArchitectureBuilder implements ModuleArchitectureBuilderInterface {

  /**
   * Constructs a module architecture builder.
   */
  public function __construct(
    private readonly CollectorManagerInterface $collectorManager,
    private readonly RelationshipManagerInterface $relationshipManager,
    private readonly SourcePathResolverInterface $sourcePathResolver,
    private readonly ModuleSourceFileCollectorInterface $sourceFileCollector,
    private readonly PhpSourceAnalyzerInterface $phpSourceAnalyzer,
    private readonly PhpDependencyResolverInterface $phpDependencyResolver,
    private readonly ModuleArchitectureCacheInterface $architectureCache,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function build(string $module_id): ?ModuleArchitecture {
    $module = $this->findModule($module_id);
    if ($module === NULL) {
      return NULL;
    }

    $cached_architecture = $this->architectureCache->get($module_id);
    if ($cached_architecture !== NULL) {
      return $cached_architecture;
    }

    $components = [];
    $owned_nodes = [];
    $services = [];
    foreach ($this->collectorManager->all() as $collector) {
      if ($collector->id() === 'modules') {
        continue;
      }

      foreach ($collector->collect() as $component) {
        if (!$component instanceof Component) {
          throw new \UnexpectedValueException('Component collectors must return Component objects.');
        }
        if ($component instanceof ServiceComponent) {
          $services[] = $component;
        }
        if (!$this->isOwnedBy($component, $module)) {
          continue;
        }

        [$node_type, $node_id] = $this->relationshipNode($component);
        $key = $this->nodeKey($node_type, $node_id);
        if (isset($owned_nodes[$key])) {
          continue;
        }

        $source_path = $this->sourcePath($component);
        $owned_nodes[$key] = TRUE;
        $components[] = new ModuleArchitectureComponent(
          type: $node_type,
          id: $node_id,
          label: $component->label,
          sourcePath: $source_path,
        );
      }
    }
    usort(
      $components,
      static fn(ModuleArchitectureComponent $first, ModuleArchitectureComponent $second): int => [
        $first->type,
        $first->id,
      ] <=> [
        $second->type,
        $second->id,
      ],
    );

    $module_node = $this->nodeKey('module', $module->id);
    $relationships = [];
    foreach ($this->relationshipManager->resolveAll() as $relationship) {
      if (!$relationship instanceof ComponentRelationship) {
        throw new \UnexpectedValueException('The relationship manager must return ComponentRelationship objects.');
      }

      $source_node = $this->nodeKey(
        $relationship->sourceType,
        $relationship->sourceId,
      );
      $target_node = $this->nodeKey(
        $relationship->targetType,
        $relationship->targetId,
      );
      if (
        $source_node === $module_node
        || isset($owned_nodes[$source_node])
        || (
          $relationship->relationship === 'navigates_to'
          && isset($owned_nodes[$target_node])
        )
      ) {
        $relationships[] = $relationship;
      }
    }

    $source_files = $this->sourceFileCollector->collect($module);
    $php_files = [];
    foreach ($source_files as $source_file) {
      if ($source_file->fileType === 'PHP') {
        $php_files[] = $this->phpSourceAnalyzer->analyze(
          $module,
          $source_file,
        );
      }
    }
    $relationships = [
      ...$relationships,
      ...$this->phpDependencyResolver->resolve($php_files, $services),
    ];

    $architecture = new ModuleArchitecture(
      $module,
      $components,
      $relationships,
      $source_files,
      $php_files,
    );
    $this->architectureCache->set($module_id, $architecture);

    return $architecture;
  }

  /**
   * Finds the requested enabled module.
   */
  private function findModule(string $module_id): ?ModuleComponent {
    foreach ($this->collectorManager->get('modules')->collect() as $module) {
      if (!$module instanceof ModuleComponent) {
        throw new \UnexpectedValueException('The module collector must return ModuleComponent objects.');
      }
      if ($module->id === $module_id) {
        return $module;
      }
    }

    return NULL;
  }

  /**
   * Determines whether a component is owned by the requested module.
   */
  private function isOwnedBy(
    Component $component,
    ModuleComponent $module,
  ): bool {
    $provider = match (TRUE) {
      $component instanceof ConfigurationComponent => $component->provider,
      $component instanceof EntityTypeComponent => $component->provider,
      $component instanceof HookComponent => $component->provider,
      $component instanceof NavigationComponent => $component->provider,
      $component instanceof PluginComponent => $component->provider,
      default => NULL,
    };
    if ($provider === $module->id) {
      return TRUE;
    }

    $source_path = $this->sourcePath($component);
    if (
      $module->sourcePath !== NULL
      && $source_path !== NULL
      && (
        $source_path === $module->sourcePath
        || str_starts_with($source_path, $module->sourcePath . '/')
      )
    ) {
      return TRUE;
    }

    return match (TRUE) {
      $component instanceof ConfigurationComponent,
      $component instanceof RouteComponent,
      $component instanceof ServiceComponent => str_starts_with(
        $component->id,
        $module->id . '.',
      ),
      default => FALSE,
    };
  }

  /**
   * Returns the component source path, resolving class-backed components.
   */
  private function sourcePath(Component $component): ?string {
    if ($component->sourcePath !== NULL) {
      return $component->sourcePath;
    }

    $class_name = match (TRUE) {
      $component instanceof PluginManagerComponent => $component->className,
      $component instanceof ServiceComponent => $component->className,
      default => NULL,
    };

    return $this->sourcePathResolver->resolve($class_name);
  }

  /**
   * Maps collector component identifiers to relationship graph node IDs.
   *
   * @return array{0: string, 1: string}
   *   The relationship node type and identifier.
   */
  private function relationshipNode(Component $component): array {
    return match (TRUE) {
      $component instanceof HookComponent => [
        'hook_implementation',
        $component->id,
      ],
      $component instanceof PluginComponent => [
        'plugin',
        $component->pluginType . ':' . $component->id,
      ],
      default => [$component->type, $component->id],
    };
  }

  /**
   * Builds a collision-safe relationship node key.
   */
  private function nodeKey(string $type, string $id): string {
    return $type . "\0" . $id;
  }

}
