<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Relationship;

use Drupal\Core\Extension\Dependency;
use Drupal\drupal_developer_assistant\Collector\ActionPluginCollector;
use Drupal\drupal_developer_assistant\Collector\BlockPluginCollector;
use Drupal\drupal_developer_assistant\Collector\ConfigurationCollector;
use Drupal\drupal_developer_assistant\Collector\HookCollector;
use Drupal\drupal_developer_assistant\Collector\ModuleCollector;
use Drupal\drupal_developer_assistant\Model\ConfigurationComponent;
use Drupal\drupal_developer_assistant\Model\HookComponent;
use Drupal\drupal_developer_assistant\Model\ModuleComponent;
use Drupal\drupal_developer_assistant\Model\PluginComponent;
use Drupal\drupal_developer_assistant\Model\ComponentRelationship;

/**
 * Resolves module dependencies and explicit component-provider relationships.
 */
final class ModuleRelationshipResolver implements RelationshipResolverInterface {

  /**
   * Constructs a module relationship resolver.
   */
  public function __construct(
    private readonly ModuleCollector $moduleCollector,
    private readonly BlockPluginCollector $blockPluginCollector,
    private readonly ActionPluginCollector $actionPluginCollector,
    private readonly HookCollector $hookCollector,
    private readonly ConfigurationCollector $configurationCollector,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'modules';
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(): array {
    return [
      ...$this->moduleDependencies(),
      ...$this->pluginProviders(),
      ...$this->hookProviders(),
      ...$this->configurationProviders(),
    ];
  }

  /**
   * Resolves dependencies declared by enabled modules.
   *
   * @return list<\Drupal\drupal_developer_assistant\Model\ComponentRelationship>
   *   Module dependency relationships.
   */
  private function moduleDependencies(): array {
    $relationships = [];
    foreach ($this->moduleCollector->collect() as $module) {
      if (!$module instanceof ModuleComponent) {
        throw new \UnexpectedValueException('The module collector must return ModuleComponent objects.');
      }

      foreach ($module->dependencies as $declaration) {
        $dependency = Dependency::createFromString($declaration);
        $metadata = ['declaration' => $declaration];
        if ($dependency->getProject() !== '') {
          $metadata['project'] = $dependency->getProject();
        }
        if ($dependency->getConstraintString() !== '') {
          $metadata['constraint'] = $dependency->getConstraintString();
        }

        $relationships[] = new ComponentRelationship(
          sourceType: 'module',
          sourceId: $module->id,
          relationship: 'depends_on',
          targetType: 'module',
          targetId: $dependency->getName(),
          metadata: $metadata,
        );
      }
    }

    return $relationships;
  }

  /**
   * Resolves providers declared by Block and Action plugin definitions.
   *
   * @return list<\Drupal\drupal_developer_assistant\Model\ComponentRelationship>
   *   Plugin provider relationships.
   */
  private function pluginProviders(): array {
    $relationships = [];
    $plugins = [
      ...$this->blockPluginCollector->collect(),
      ...$this->actionPluginCollector->collect(),
    ];
    foreach ($plugins as $plugin) {
      if (!$plugin instanceof PluginComponent) {
        throw new \UnexpectedValueException('Plugin collectors must return PluginComponent objects.');
      }
      if ($plugin->provider === NULL) {
        continue;
      }

      $metadata = [
        'label' => $plugin->label,
        'plugin_type' => $plugin->pluginType,
      ];
      if ($plugin->className !== NULL) {
        $metadata['class'] = $plugin->className;
      }
      if ($plugin->sourcePath !== NULL) {
        $metadata['source_path'] = $plugin->sourcePath;
      }
      $relationships[] = new ComponentRelationship(
        sourceType: 'plugin',
        sourceId: $plugin->pluginType . ':' . $plugin->id,
        relationship: 'provided_by',
        targetType: 'module',
        targetId: $plugin->provider,
        metadata: $metadata,
      );
    }

    return $relationships;
  }

  /**
   * Resolves the module represented by each compiled hook implementation.
   *
   * @return list<\Drupal\drupal_developer_assistant\Model\ComponentRelationship>
   *   Hook provider relationships.
   */
  private function hookProviders(): array {
    $relationships = [];
    foreach ($this->hookCollector->collect() as $hook) {
      if (!$hook instanceof HookComponent) {
        throw new \UnexpectedValueException('The hook collector must return HookComponent objects.');
      }

      $metadata = [
        'callable' => $hook->callable,
        'hook' => $hook->hookName,
        'style' => $hook->implementationType,
      ];
      if ($hook->sourcePath !== NULL) {
        $metadata['source_path'] = $hook->sourcePath;
      }
      $relationships[] = new ComponentRelationship(
        sourceType: 'hook_implementation',
        sourceId: $hook->id,
        relationship: 'provided_by',
        targetType: 'module',
        targetId: $hook->provider,
        metadata: $metadata,
      );
    }

    return $relationships;
  }

  /**
   * Resolves providers for active configuration entities.
   *
   * @return list<\Drupal\drupal_developer_assistant\Model\ComponentRelationship>
   *   Configuration provider relationships.
   */
  private function configurationProviders(): array {
    $relationships = [];
    foreach ($this->configurationCollector->collect() as $configuration) {
      if (!$configuration instanceof ConfigurationComponent) {
        throw new \UnexpectedValueException('The configuration collector must return ConfigurationComponent objects.');
      }
      if ($configuration->provider === NULL) {
        continue;
      }

      $metadata = ['configuration_type' => $configuration->configurationType];
      if ($configuration->entityTypeId !== NULL) {
        $metadata['entity_type'] = $configuration->entityTypeId;
      }
      $relationships[] = new ComponentRelationship(
        sourceType: 'configuration',
        sourceId: $configuration->id,
        relationship: 'provided_by',
        targetType: 'module',
        targetId: $configuration->provider,
        metadata: $metadata,
      );
    }

    return $relationships;
  }

}
