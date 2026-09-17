<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Url;
use Drupal\drupal_developer_assistant\Analyzer\PhpSourceAnalyzerInterface;
use Drupal\drupal_developer_assistant\Architecture\ModuleArchitectureBuilderInterface;
use Drupal\drupal_developer_assistant\Collector\CollectorManagerInterface;
use Drupal\drupal_developer_assistant\Collector\ModuleSourceFileCollectorInterface;
use Drupal\drupal_developer_assistant\Component\ModuleComponentDetailsResolverInterface;
use Drupal\drupal_developer_assistant\Form\ComponentFilterForm;
use Drupal\drupal_developer_assistant\Form\ModuleArchitectureRefreshForm;
use Drupal\drupal_developer_assistant\Model\ActionPluginComponent;
use Drupal\drupal_developer_assistant\Model\ComponentRelationship;
use Drupal\drupal_developer_assistant\Model\ConfigurationComponent;
use Drupal\drupal_developer_assistant\Model\ControllerComponent;
use Drupal\drupal_developer_assistant\Model\EntityTypeComponent;
use Drupal\drupal_developer_assistant\Model\FormComponent;
use Drupal\drupal_developer_assistant\Model\HookComponent;
use Drupal\drupal_developer_assistant\Model\ModuleComponent;
use Drupal\drupal_developer_assistant\Model\PluginComponent;
use Drupal\drupal_developer_assistant\Model\PluginManagerComponent;
use Drupal\drupal_developer_assistant\Model\PhpFunction;
use Drupal\drupal_developer_assistant\Model\PhpMethod;
use Drupal\drupal_developer_assistant\Model\PhpParameter;
use Drupal\drupal_developer_assistant\Model\PhpProperty;
use Drupal\drupal_developer_assistant\Model\PhpSymbol;
use Drupal\drupal_developer_assistant\Model\RouteComponent;
use Drupal\drupal_developer_assistant\Model\ServiceComponent;
use Drupal\drupal_developer_assistant\Relationship\RelationshipManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Builds pages for the Drupal Developer Assistant.
 */
final class InspectorController extends ControllerBase {

  /**
   * Number of module components displayed on one page.
   */
  private const int COMPONENTS_PER_PAGE = 25;

  /**
   * Number of module source files displayed on one page.
   */
  private const int SOURCE_FILES_PER_PAGE = 25;

  /**
   * Number of PHP files structurally analyzed on one page.
   */
  private const int PHP_FILES_PER_PAGE = 10;

  /**
   * Number of module relationships displayed on one page.
   */
  private const int RELATIONSHIPS_PER_PAGE = 25;

  /**
   * Constructs an inspector controller.
   */
  public function __construct(
    private readonly CollectorManagerInterface $collectorManager,
    private readonly RelationshipManagerInterface $relationshipManager,
    private readonly ModuleArchitectureBuilderInterface $moduleArchitectureBuilder,
    private readonly ModuleSourceFileCollectorInterface $sourceFileCollector,
    private readonly PhpSourceAnalyzerInterface $phpSourceAnalyzer,
    private readonly PagerManagerInterface $pagerManager,
    private readonly FormBuilderInterface $componentFormBuilder,
    private readonly ComponentFilterForm $filterForm,
    private readonly ModuleArchitectureRefreshForm $architectureRefreshForm,
    private readonly ModuleComponentDetailsResolverInterface $componentDetailsResolver,
  ) {}

  /**
   * Builds the inspector overview page.
   *
   * @return array
   *   A render array for the overview page.
   */
  public function overview(): array {
    return [
      '#theme' => 'drupal_developer_assistant_overview',
      '#intro' => $this->t('Choose what you want to do.'),
      '#sections' => [
        [
          'title' => $this->t('Ask the AI Assistant'),
          'description' => $this->t('Ask a developer question. The agent gathers bounded Drupal evidence, inspects source when needed, and validates its final answer.'),
          'url' => $this->newAgentConversationUrl(),
        ],
        [
          'title' => $this->t('Inspect and analyze a module'),
          'description' => $this->t('Main workflow: choose a module, inspect its architecture, prepare grounded context, and analyze it with AI.'),
          'url' => Url::fromRoute('drupal_developer_assistant.modules'),
        ],
        [
          'title' => $this->t('Browse the Drupal catalog'),
          'description' => $this->t('Reference views of collected services, plugins, entities, routes, controllers, forms, hooks, configuration, and relationships.'),
          'url' => Url::fromRoute('drupal_developer_assistant.catalog'),
        ],
      ],
    ];
  }

  /**
   * Builds the read-only component catalog landing page.
   *
   * @return array
   *   Render array linking to global Drupal component indexes.
   */
  public function catalog(): array {
    return [
      '#theme' => 'drupal_developer_assistant_overview',
      '#intro' => $this->t('These pages are read-only indexes of Drupal data. Use Modules when you want the complete inspection and AI-analysis workflow.'),
      '#sections' => [
        [
          'title' => $this->t('Services'),
          'description' => $this->t('Browse service IDs, classes, aliases, and injected service references.'),
          'url' => Url::fromRoute('drupal_developer_assistant.services'),
        ],
        [
          'title' => $this->t('Plugins'),
          'description' => $this->t('Browse plugin managers and discovered plugin definitions.'),
          'url' => Url::fromRoute('drupal_developer_assistant.plugins'),
        ],
        [
          'title' => $this->t('Entity types'),
          'description' => $this->t('Browse Drupal content and configuration entity definitions.'),
          'url' => Url::fromRoute('drupal_developer_assistant.entities'),
        ],
        [
          'title' => $this->t('Routes'),
          'description' => $this->t('Browse route names, paths, handlers, and access requirements.'),
          'url' => Url::fromRoute('drupal_developer_assistant.routes'),
        ],
        [
          'title' => $this->t('Controllers'),
          'description' => $this->t('Browse controller callables discovered from routes.'),
          'url' => Url::fromRoute('drupal_developer_assistant.controllers'),
        ],
        [
          'title' => $this->t('Forms'),
          'description' => $this->t('Browse form classes discovered from routes.'),
          'url' => Url::fromRoute('drupal_developer_assistant.forms'),
        ],
        [
          'title' => $this->t('Hooks'),
          'description' => $this->t('Browse procedural and object-oriented hook implementations.'),
          'url' => Url::fromRoute('drupal_developer_assistant.hooks'),
        ],
        [
          'title' => $this->t('Configuration'),
          'description' => $this->t('Browse active configuration objects and their dependencies.'),
          'url' => Url::fromRoute('drupal_developer_assistant.configuration'),
        ],
        [
          'title' => $this->t('Relationships'),
          'description' => $this->t('Browse collected connections between Drupal components.'),
          'url' => Url::fromRoute('drupal_developer_assistant.architecture'),
        ],
      ],
    ];
  }

  /**
   * Builds the enabled-modules page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array
   *   A render array for the enabled-modules page.
   */
  public function modules(Request $request): array {
    $filter = trim($request->query->getString('filter'));
    $module_rows = [];
    foreach ($this->collectorManager->get('modules')->collect() as $module) {
      if (!$module instanceof ModuleComponent) {
        throw new \UnexpectedValueException('The modules collector must return ModuleComponent objects.');
      }

      if (!$this->matchesFilter($filter, [
        $module->id,
        $module->label,
        $module->sourcePath ?? '',
        $module->package ?? '',
        $module->version ?? '',
        ...$module->dependencies,
      ])) {
        continue;
      }

      $module_rows[] = [
        $module->label,
        $module->id,
        $module->type,
        $module->sourcePath ?? '',
        $module->package ?? '',
        $module->version ?? '',
        implode(', ', $module->dependencies),
        [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Inspect and analyze'),
            '#url' => Url::fromRoute(
              'drupal_developer_assistant.module_overview',
              ['module_id' => $module->id],
            ),
          ],
        ],
      ];
    }

    return [
      'intro' => [
        '#markup' => $this->t('This is the main workflow. Choose Inspect and analyze to open one module, understand its architecture, prepare AI context, and ask grounded questions.'),
      ],
      'filter' => $this->componentFormBuilder->getForm(
        $this->filterForm,
        'drupal_developer_assistant.modules',
        $filter,
      ),
      'summary' => [
        '#markup' => $this->formatPlural(
          count($module_rows),
          '1 module found.',
          '@count modules found.',
        ),
      ],
      'modules' => [
        '#type' => 'table',
        '#caption' => $this->t('Enabled modules'),
        '#header' => [
          $this->t('Name'),
          $this->t('Machine name'),
          $this->t('Type'),
          $this->t('Path'),
          $this->t('Package'),
          $this->t('Version'),
          $this->t('Dependencies'),
          $this->t('Next step'),
        ],
        '#rows' => $module_rows,
        '#empty' => $this->t('No enabled modules match the filter.'),
      ],
    ];
  }

  /**
   * Builds a compact starting page for one enabled module.
   *
   * @param string $module_id
   *   The module machine name from the route.
   *
   * @return array
   *   A render array containing module facts, counts, and next actions.
   */
  public function moduleOverview(string $module_id): array {
    $architecture = $this->moduleArchitectureBuilder->build($module_id);
    if ($architecture === NULL) {
      throw new NotFoundHttpException(sprintf('Enabled module "%s" was not found.', $module_id));
    }

    $component_counts = [];
    foreach ($architecture->components as $component) {
      $component_counts[$component->type]
        = ($component_counts[$component->type] ?? 0) + 1;
    }
    ksort($component_counts, SORT_STRING);

    $inventory_rows = [
      [$this->t('All architecture components'), count($architecture->components)],
    ];
    foreach ($component_counts as $type => $count) {
      $inventory_rows[] = [$type, $count];
    }
    $inventory_rows[] = [$this->t('Source files'), count($architecture->sourceFiles)];
    $inventory_rows[] = [$this->t('PHP files'), count($architecture->phpFiles)];
    $inventory_rows[] = [$this->t('Relationships'), count($architecture->relationships)];

    return [
      'back' => [
        '#type' => 'link',
        '#title' => $this->t('Back to modules'),
        '#url' => Url::fromRoute('drupal_developer_assistant.modules'),
      ],
      'intro' => [
        '#markup' => $this->t('This compact overview is the starting point for inspecting and analyzing the module. Detailed files and code structures are shown only when you open the architecture report.'),
      ],
      'module' => [
        '#type' => 'table',
        '#caption' => $this->t('Module'),
        '#header' => [
          $this->t('Name'),
          $this->t('Machine name'),
          $this->t('Path'),
          $this->t('Package'),
          $this->t('Version'),
          $this->t('Dependencies'),
        ],
        '#rows' => [
          [
            $architecture->module->label,
            $architecture->module->id,
            $architecture->module->sourcePath ?? '',
            $architecture->module->package ?? '',
            $architecture->module->version ?? '',
            $architecture->module->dependencies === []
              ? $this->t('None')
              : implode(', ', $architecture->module->dependencies),
          ],
        ],
      ],
      'inventory' => [
        '#type' => 'table',
        '#caption' => $this->t('Inventory summary'),
        '#header' => [
          $this->t('Item'),
          $this->t('Count'),
        ],
        '#rows' => $inventory_rows,
      ],
      'architecture_cache' => [
        '#type' => 'details',
        '#title' => $this->t('Architecture cache'),
        'refresh' => $this->componentFormBuilder->getForm(
          $this->architectureRefreshForm,
          $module_id,
        ),
      ],
      'next_steps' => [
        '#type' => 'fieldset',
        '#title' => $this->t('Continue the module workflow'),
        'help' => [
          '#markup' => $this->t('First inspect the complete architecture when you need technical details. Then prepare the bounded context used for grounded AI analysis.'),
        ],
        'actions' => [
          '#type' => 'actions',
          'components' => [
            '#type' => 'link',
            '#title' => $this->t('Browse components'),
            '#url' => Url::fromRoute(
              'drupal_developer_assistant.module_components',
              ['module_id' => $module_id],
            ),
            '#attributes' => ['class' => ['button']],
          ],
          'source_files' => [
            '#type' => 'link',
            '#title' => $this->t('Browse source files'),
            '#url' => Url::fromRoute(
              'drupal_developer_assistant.module_source_files',
              ['module_id' => $module_id],
            ),
            '#attributes' => ['class' => ['button']],
          ],
          'php_structure' => [
            '#type' => 'link',
            '#title' => $this->t('Inspect PHP structure'),
            '#url' => Url::fromRoute(
              'drupal_developer_assistant.module_php_structure',
              ['module_id' => $module_id],
            ),
            '#attributes' => ['class' => ['button']],
          ],
          'relationships' => [
            '#type' => 'link',
            '#title' => $this->t('View relationships'),
            '#url' => Url::fromRoute(
              'drupal_developer_assistant.module_relationships',
              ['module_id' => $module_id],
            ),
            '#attributes' => ['class' => ['button']],
          ],
          'context' => [
            '#type' => 'link',
            '#title' => $this->t('Preview AI context'),
            '#url' => Url::fromRoute(
              'drupal_developer_assistant.module_context',
              ['module_id' => $module_id],
            ),
            '#attributes' => ['class' => ['button', 'button--primary']],
          ],
        ],
      ],
    ];
  }

  /**
   * Builds the services page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array
   *   A render array for the services page.
   */
  public function services(Request $request): array {
    $filter = trim($request->query->getString('filter'));
    $service_rows = [];
    foreach ($this->collectorManager->get('services')->collect() as $service) {
      if (!$service instanceof ServiceComponent) {
        throw new \UnexpectedValueException('The services collector must return ServiceComponent objects.');
      }

      $referenced_service_ids = array_merge(...array_values(
        $service->references,
      ));
      if (!$this->matchesFilter($filter, [
        $service->id,
        $service->className ?? '',
        $service->aliasTarget ?? '',
        ...$referenced_service_ids,
      ])) {
        continue;
      }

      $service_rows[] = [
        $service->id,
        $service->className ?? '',
        $service->aliasTarget ?? '',
        implode(', ', $referenced_service_ids),
      ];
    }

    return [
      'filter' => $this->componentFormBuilder->getForm(
        $this->filterForm,
        'drupal_developer_assistant.services',
        $filter,
      ),
      'summary' => [
        '#markup' => $this->formatPlural(
          count($service_rows),
          '1 service found.',
          '@count services found.',
        ),
      ],
      'services' => [
        '#type' => 'table',
        '#caption' => $this->t('Services'),
        '#header' => [
          $this->t('Service ID'),
          $this->t('Class'),
          $this->t('Alias target'),
          $this->t('Referenced services'),
        ],
        '#rows' => $service_rows,
        '#empty' => $this->t('No services match the filter.'),
      ],
    ];
  }

  /**
   * Builds the plugin-types page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array
   *   A render array for the plugin-types page.
   */
  public function plugins(Request $request): array {
    $filter = trim($request->query->getString('filter'));
    $plugin_rows = [];
    foreach ($this->collectorManager->get('plugin_managers')->collect() as $manager) {
      if (!$manager instanceof PluginManagerComponent) {
        throw new \UnexpectedValueException('The plugin manager collector must return PluginManagerComponent objects.');
      }

      if (!$this->matchesFilter($filter, [
        $manager->pluginType,
        $manager->managerServiceId,
        $manager->className ?? '',
      ])) {
        continue;
      }

      $detail_route = [
        'action' => 'drupal_developer_assistant.plugins.action',
        'block' => 'drupal_developer_assistant.plugins.block',
      ][$manager->pluginType] ?? NULL;

      $plugin_rows[] = [
        $manager->pluginType,
        $manager->managerServiceId,
        $manager->className ?? '',
        $detail_route !== NULL
          ? [
            'data' => [
              '#type' => 'link',
              '#title' => $this->t('View plugins'),
              '#url' => Url::fromRoute($detail_route),
            ],
          ]
          : '',
      ];
    }

    return [
      'filter' => $this->componentFormBuilder->getForm(
        $this->filterForm,
        'drupal_developer_assistant.plugins',
        $filter,
      ),
      'summary' => [
        '#markup' => $this->formatPlural(
          count($plugin_rows),
          '1 plugin type found.',
          '@count plugin types found.',
        ),
      ],
      'plugin_managers' => [
        '#type' => 'table',
        '#caption' => $this->t('Plugin types and managers'),
        '#header' => [
          $this->t('Plugin type'),
          $this->t('Manager service ID'),
          $this->t('Manager class'),
          $this->t('Operations'),
        ],
        '#rows' => $plugin_rows,
        '#empty' => $this->t('No plugin types match the filter.'),
      ],
    ];
  }

  /**
   * Builds the Block plugins page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array
   *   A render array for the Block plugins page.
   */
  public function blockPlugins(Request $request): array {
    $filter = trim($request->query->getString('filter'));
    $plugin_rows = [];
    foreach ($this->collectorManager->get('plugins.block')->collect() as $plugin) {
      if (!$plugin instanceof PluginComponent) {
        throw new \UnexpectedValueException('The Block plugin collector must return PluginComponent objects.');
      }

      if (!$this->matchesFilter($filter, [
        $plugin->id,
        $plugin->label,
        $plugin->pluginType,
        $plugin->className ?? '',
        $plugin->provider ?? '',
        $plugin->sourcePath ?? '',
      ])) {
        continue;
      }

      $plugin_rows[] = [
        $plugin->label,
        $plugin->id,
        $plugin->pluginType,
        $plugin->provider ?? '',
        $plugin->className ?? '',
        $plugin->sourcePath ?? '',
      ];
    }

    return [
      'filter' => $this->componentFormBuilder->getForm(
        $this->filterForm,
        'drupal_developer_assistant.plugins.block',
        $filter,
      ),
      'summary' => [
        '#markup' => $this->formatPlural(
          count($plugin_rows),
          '1 Block plugin found.',
          '@count Block plugins found.',
        ),
      ],
      'plugins' => [
        '#type' => 'table',
        '#caption' => $this->t('Block plugins'),
        '#header' => [
          $this->t('Label'),
          $this->t('Plugin ID'),
          $this->t('Plugin type'),
          $this->t('Provider'),
          $this->t('Class'),
          $this->t('Source path'),
        ],
        '#rows' => $plugin_rows,
        '#empty' => $this->t('No Block plugins match the filter.'),
      ],
    ];
  }

  /**
   * Builds the Action plugins page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array
   *   A render array for the Action plugins page.
   */
  public function actionPlugins(Request $request): array {
    $filter = trim($request->query->getString('filter'));
    $plugin_rows = [];
    foreach ($this->collectorManager->get('plugins.action')->collect() as $plugin) {
      if (!$plugin instanceof ActionPluginComponent) {
        throw new \UnexpectedValueException('The Action plugin collector must return ActionPluginComponent objects.');
      }

      if (!$this->matchesFilter($filter, [
        $plugin->id,
        $plugin->label,
        $plugin->pluginType,
        $plugin->targetType ?? '',
        $plugin->className ?? '',
        $plugin->provider ?? '',
        $plugin->sourcePath ?? '',
      ])) {
        continue;
      }

      $plugin_rows[] = [
        $plugin->label,
        $plugin->id,
        $plugin->targetType ?? '',
        $plugin->provider ?? '',
        $plugin->className ?? '',
        $plugin->sourcePath ?? '',
      ];
    }

    return [
      'filter' => $this->componentFormBuilder->getForm(
        $this->filterForm,
        'drupal_developer_assistant.plugins.action',
        $filter,
      ),
      'summary' => [
        '#markup' => $this->formatPlural(
          count($plugin_rows),
          '1 Action plugin found.',
          '@count Action plugins found.',
        ),
      ],
      'plugins' => [
        '#type' => 'table',
        '#caption' => $this->t('Action plugins'),
        '#header' => [
          $this->t('Label'),
          $this->t('Plugin ID'),
          $this->t('Target type'),
          $this->t('Provider'),
          $this->t('Class'),
          $this->t('Source path'),
        ],
        '#rows' => $plugin_rows,
        '#empty' => $this->t('No Action plugins match the filter.'),
      ],
    ];
  }

  /**
   * Builds the entity-types page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array
   *   A render array for the entity-types page.
   */
  public function entities(Request $request): array {
    $filter = trim($request->query->getString('filter'));
    $entity_rows = [];
    foreach ($this->collectorManager->get('entity_types')->collect() as $entity_type) {
      if (!$entity_type instanceof EntityTypeComponent) {
        throw new \UnexpectedValueException('The entity type collector must return EntityTypeComponent objects.');
      }

      if (!$this->matchesFilter($filter, [
        $entity_type->id,
        $entity_type->label,
        $entity_type->entityKind,
        $entity_type->entityClass ?? '',
        $entity_type->provider ?? '',
        $entity_type->baseTable ?? '',
        $entity_type->configPrefix ?? '',
        $entity_type->sourcePath ?? '',
        ...array_keys($entity_type->handlerClasses),
        ...array_values($entity_type->handlerClasses),
      ])) {
        continue;
      }

      $handler_items = [];
      foreach ($entity_type->handlerClasses as $handler_id => $handler_class) {
        $handler_items[] = $handler_id . ': ' . $handler_class;
      }

      $entity_rows[] = [
        $entity_type->label,
        $entity_type->id,
        $entity_type->entityKind,
        $entity_type->provider ?? '',
        $entity_type->entityClass ?? '',
        $entity_type->baseTable ?? '',
        $entity_type->configPrefix ?? '',
        [
          'data' => [
            '#theme' => 'item_list',
            '#items' => $handler_items,
          ],
        ],
        $entity_type->sourcePath ?? '',
      ];
    }

    return [
      'filter' => $this->componentFormBuilder->getForm(
        $this->filterForm,
        'drupal_developer_assistant.entities',
        $filter,
      ),
      'summary' => [
        '#markup' => $this->formatPlural(
          count($entity_rows),
          '1 entity type found.',
          '@count entity types found.',
        ),
      ],
      'entity_types' => [
        '#type' => 'table',
        '#caption' => $this->t('Entity types'),
        '#header' => [
          $this->t('Label'),
          $this->t('Entity type ID'),
          $this->t('Kind'),
          $this->t('Provider'),
          $this->t('Entity class'),
          $this->t('Base table'),
          $this->t('Config prefix'),
          $this->t('Handlers'),
          $this->t('Source path'),
        ],
        '#rows' => $entity_rows,
        '#empty' => $this->t('No entity types match the filter.'),
      ],
    ];
  }

  /**
   * Builds the routes page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array
   *   A render array for the routes page.
   */
  public function routes(Request $request): array {
    $filter = trim($request->query->getString('filter'));
    $route_rows = [];
    foreach ($this->collectorManager->get('routes')->collect() as $route) {
      if (!$route instanceof RouteComponent) {
        throw new \UnexpectedValueException('The route collector must return RouteComponent objects.');
      }

      if (!$this->matchesFilter($filter, [
        $route->id,
        $route->label,
        ...$route->methods,
        $route->targetType ?? '',
        $route->target ?? '',
        $route->targetClass ?? '',
        ...array_keys($route->requirements),
        ...array_values($route->requirements),
        $route->adminRoute ? 'admin' : 'non-admin',
        $route->sourcePath ?? '',
      ])) {
        continue;
      }

      $requirement_items = [];
      foreach ($route->requirements as $name => $requirement) {
        $requirement_items[] = $name . ': ' . $requirement;
      }

      $route_rows[] = [
        $route->id,
        $route->label,
        $route->methods === [] ? $this->t('Any') : implode(', ', $route->methods),
        $route->targetType ?? '',
        $route->target ?? '',
        $route->targetClass ?? '',
        [
          'data' => [
            '#theme' => 'item_list',
            '#items' => $requirement_items,
          ],
        ],
        $route->adminRoute ? $this->t('Yes') : $this->t('No'),
        $route->sourcePath ?? '',
      ];
    }

    return [
      'filter' => $this->componentFormBuilder->getForm(
        $this->filterForm,
        'drupal_developer_assistant.routes',
        $filter,
      ),
      'summary' => [
        '#markup' => $this->formatPlural(
          count($route_rows),
          '1 route found.',
          '@count routes found.',
        ),
      ],
      'routes' => [
        '#type' => 'table',
        '#caption' => $this->t('Routes'),
        '#header' => [
          $this->t('Route name'),
          $this->t('Path'),
          $this->t('Methods'),
          $this->t('Target type'),
          $this->t('Raw target'),
          $this->t('Resolved class'),
          $this->t('Requirements'),
          $this->t('Admin route'),
          $this->t('Source path'),
        ],
        '#rows' => $route_rows,
        '#empty' => $this->t('No routes match the filter.'),
      ],
    ];
  }

  /**
   * Builds the controllers page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array
   *   A render array for the controllers page.
   */
  public function controllers(Request $request): array {
    $filter = trim($request->query->getString('filter'));
    $controller_rows = [];
    foreach ($this->collectorManager->get('controllers')->collect() as $controller) {
      if (!$controller instanceof ControllerComponent) {
        throw new \UnexpectedValueException('The controller collector must return ControllerComponent objects.');
      }

      if (!$this->matchesFilter($filter, [
        $controller->id,
        $controller->label,
        ...array_keys($controller->routePaths),
        ...array_values($controller->routePaths),
        ...array_values($controller->routeCallbacks),
        ...$controller->serviceIds,
        $controller->sourcePath ?? '',
      ])) {
        continue;
      }

      $route_items = [];
      foreach ($controller->routePaths as $route_name => $route_path) {
        $callback = $controller->routeCallbacks[$route_name];
        $route_items[] = $route_name . ': ' . $route_path . ' → ' . $callback . '()';
      }

      $controller_rows[] = [
        $controller->label,
        $controller->className,
        implode(', ', $controller->serviceIds),
        [
          'data' => [
            '#theme' => 'item_list',
            '#items' => $route_items,
          ],
        ],
        $controller->sourcePath ?? '',
      ];
    }

    return [
      'filter' => $this->componentFormBuilder->getForm(
        $this->filterForm,
        'drupal_developer_assistant.controllers',
        $filter,
      ),
      'summary' => [
        '#markup' => $this->formatPlural(
          count($controller_rows),
          '1 controller found.',
          '@count controllers found.',
        ),
      ],
      'controllers' => [
        '#type' => 'table',
        '#caption' => $this->t('Controllers'),
        '#header' => [
          $this->t('Controller'),
          $this->t('Class'),
          $this->t('Service IDs'),
          $this->t('Routes and callbacks'),
          $this->t('Source path'),
        ],
        '#rows' => $controller_rows,
        '#empty' => $this->t('No controllers match the filter.'),
      ],
    ];
  }

  /**
   * Builds the forms page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array
   *   A render array for the forms page.
   */
  public function forms(Request $request): array {
    $filter = trim($request->query->getString('filter'));
    $form_rows = [];
    foreach ($this->collectorManager->get('forms')->collect() as $form) {
      if (!$form instanceof FormComponent) {
        throw new \UnexpectedValueException('The form collector must return FormComponent objects.');
      }

      if (!$this->matchesFilter($filter, [
        $form->id,
        $form->label,
        ...array_keys($form->routePaths),
        ...array_values($form->routePaths),
        ...array_values($form->routeTypes),
        ...array_values($form->routeTargets),
        $form->sourcePath ?? '',
      ])) {
        continue;
      }

      $route_items = [];
      foreach ($form->routePaths as $route_name => $route_path) {
        $route_items[] = sprintf(
          '%s: %s → %s: %s',
          $route_name,
          $route_path,
          $form->routeTypes[$route_name],
          $form->routeTargets[$route_name],
        );
      }

      $form_rows[] = [
        $form->label,
        $form->className,
        implode(', ', array_unique($form->routeTypes)),
        [
          'data' => [
            '#theme' => 'item_list',
            '#items' => $route_items,
          ],
        ],
        $form->sourcePath ?? '',
      ];
    }

    return [
      'filter' => $this->componentFormBuilder->getForm(
        $this->filterForm,
        'drupal_developer_assistant.forms',
        $filter,
      ),
      'summary' => [
        '#markup' => $this->formatPlural(
          count($form_rows),
          '1 form found.',
          '@count forms found.',
        ),
      ],
      'forms' => [
        '#type' => 'table',
        '#caption' => $this->t('Forms'),
        '#header' => [
          $this->t('Form'),
          $this->t('Class'),
          $this->t('Form types'),
          $this->t('Routes and targets'),
          $this->t('Source path'),
        ],
        '#rows' => $form_rows,
        '#empty' => $this->t('No forms match the filter.'),
      ],
    ];
  }

  /**
   * Builds the hook-implementations page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array
   *   A render array for the hook-implementations page.
   */
  public function hooks(Request $request): array {
    $filter = trim($request->query->getString('filter'));
    $hook_rows = [];
    foreach ($this->collectorManager->get('hooks')->collect() as $hook) {
      if (!$hook instanceof HookComponent) {
        throw new \UnexpectedValueException('The hook collector must return HookComponent objects.');
      }

      if (!$this->matchesFilter($filter, [
        $hook->id,
        $hook->label,
        $hook->hookName,
        $hook->provider,
        $hook->implementationType,
        $hook->callable,
        $hook->className ?? '',
        $hook->methodName ?? '',
        (string) $hook->executionOrder,
        $hook->sourcePath ?? '',
      ])) {
        continue;
      }

      $hook_rows[] = [
        $hook->label . '()',
        $hook->provider,
        $hook->implementationType,
        $hook->callable,
        $hook->executionOrder,
        $hook->sourcePath ?? '',
      ];
    }

    return [
      'filter' => $this->componentFormBuilder->getForm(
        $this->filterForm,
        'drupal_developer_assistant.hooks',
        $filter,
      ),
      'summary' => [
        '#markup' => $this->formatPlural(
          count($hook_rows),
          '1 hook implementation found.',
          '@count hook implementations found.',
        ),
      ],
      'hooks' => [
        '#type' => 'table',
        '#caption' => $this->t('Hook implementations'),
        '#header' => [
          $this->t('Hook'),
          $this->t('Module'),
          $this->t('Style'),
          $this->t('Callable'),
          $this->t('Execution order'),
          $this->t('Source path'),
        ],
        '#rows' => $hook_rows,
        '#empty' => $this->t('No hook implementations match the filter.'),
      ],
    ];
  }

  /**
   * Builds the active-configuration page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array
   *   A render array for the active-configuration page.
   */
  public function configuration(Request $request): array {
    $filter = trim($request->query->getString('filter'));
    $configuration_rows = [];
    foreach ($this->collectorManager->get('configuration')->collect() as $configuration) {
      if (!$configuration instanceof ConfigurationComponent) {
        throw new \UnexpectedValueException('The configuration collector must return ConfigurationComponent objects.');
      }

      $dependency_values = [];
      foreach ($configuration->dependencies as $type => $dependencies) {
        $dependency_values[] = $type;
        array_push($dependency_values, ...$dependencies);
      }

      if (!$this->matchesFilter($filter, [
        $configuration->id,
        $configuration->configurationType,
        $configuration->entityTypeId ?? '',
        $configuration->provider ?? '',
        $configuration->entityTypeClass ?? '',
        ...$configuration->topLevelKeys,
        ...$dependency_values,
        $configuration->sourcePath ?? '',
      ])) {
        continue;
      }

      $dependency_items = [];
      foreach ($configuration->dependencies as $type => $dependencies) {
        $dependency_items[] = $type . ': ' . implode(', ', $dependencies);
      }

      $configuration_rows[] = [
        $configuration->id,
        $configuration->configurationType,
        $configuration->entityTypeId ?? '',
        $configuration->provider ?? '',
        $configuration->entityTypeClass ?? '',
        implode(', ', $configuration->topLevelKeys),
        [
          'data' => [
            '#theme' => 'item_list',
            '#items' => $dependency_items,
          ],
        ],
        $configuration->sourcePath ?? '',
      ];
    }

    return [
      'filter' => $this->componentFormBuilder->getForm(
        $this->filterForm,
        'drupal_developer_assistant.configuration',
        $filter,
      ),
      'summary' => [
        '#markup' => $this->formatPlural(
          count($configuration_rows),
          '1 configuration object found.',
          '@count configuration objects found.',
        ),
      ],
      'configuration' => [
        '#type' => 'table',
        '#caption' => $this->t('Active configuration'),
        '#header' => [
          $this->t('Configuration name'),
          $this->t('Kind'),
          $this->t('Entity type'),
          $this->t('Provider'),
          $this->t('Entity type class'),
          $this->t('Top-level keys'),
          $this->t('Dependencies'),
          $this->t('Entity type source'),
        ],
        '#rows' => $configuration_rows,
        '#empty' => $this->t('No configuration objects match the filter.'),
      ],
    ];
  }

  /**
   * Builds the component-relationships page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array
   *   A render array for the component-relationships page.
   */
  public function architecture(Request $request): array {
    $filter = trim($request->query->getString('filter'));
    $relationship_rows = [];
    foreach ($this->relationshipManager->resolveAll() as $relationship) {
      if (!$relationship instanceof ComponentRelationship) {
        throw new \UnexpectedValueException('The relationship manager must return ComponentRelationship objects.');
      }

      if (!$this->matchesFilter($filter, [
        $relationship->sourceType,
        $relationship->sourceId,
        $relationship->relationship,
        $relationship->targetType,
        $relationship->targetId,
        ...array_keys($relationship->metadata),
        ...array_values($relationship->metadata),
      ])) {
        continue;
      }

      $relationship_rows[] = $this->relationshipRow($relationship);
    }

    return [
      'filter' => $this->componentFormBuilder->getForm(
        $this->filterForm,
        'drupal_developer_assistant.architecture',
        $filter,
      ),
      'summary' => [
        '#markup' => $this->formatPlural(
          count($relationship_rows),
          '1 relationship found.',
          '@count relationships found.',
        ),
      ],
      'relationships' => [
        '#type' => 'table',
        '#caption' => $this->t('Component relationships'),
        '#header' => [
          $this->t('Source'),
          $this->t('Relationship'),
          $this->t('Target'),
          $this->t('Metadata'),
        ],
        '#rows' => $relationship_rows,
        '#empty' => $this->t('No component relationships match the filter.'),
      ],
    ];
  }

  /**
   * Builds the discovered-components page for one enabled module.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param string $module_id
   *   The module machine name from the route.
   *
   * @return array
   *   A render array containing filtered component counts and links.
   */
  public function moduleComponents(
    Request $request,
    string $module_id,
  ): array {
    $architecture = $this->moduleArchitectureBuilder->build($module_id);
    if ($architecture === NULL) {
      throw new NotFoundHttpException(sprintf('Enabled module "%s" was not found.', $module_id));
    }

    $filter = trim($request->query->getString('filter'));
    $components = array_values(array_filter(
      $architecture->components,
      fn($component): bool => $this->matchesFilter($filter, [
        $component->type,
        $component->id,
        $component->label,
        $component->sourcePath ?? '',
      ]),
    ));

    $component_counts = [];
    foreach ($components as $component) {
      $component_counts[$component->type]
        = ($component_counts[$component->type] ?? 0) + 1;
    }
    ksort($component_counts, SORT_STRING);

    $pager = $this->pagerManager->createPager(
      count($components),
      self::COMPONENTS_PER_PAGE,
    );
    $page_components = array_slice(
      $components,
      $pager->getCurrentPage() * self::COMPONENTS_PER_PAGE,
      self::COMPONENTS_PER_PAGE,
    );

    $component_rows = [];
    foreach ($page_components as $component) {
      $component_rows[] = [
        $component->type,
        [
          'data' => [
            '#type' => 'link',
            '#title' => $component->id,
            '#url' => Url::fromRoute(
              'drupal_developer_assistant.module_component',
              [
                'module_id' => $module_id,
                'component_reference' => $this->componentDetailsResolver
                  ->referenceId(
                    $module_id,
                    $component->type,
                    $component->id,
                ),
              ],
            ),
          ],
        ],
        $component->label,
        $component->sourcePath ?? '',
      ];
    }

    $summary_rows = [];
    foreach ($component_counts as $type => $count) {
      $summary_rows[] = [$type, $count];
    }

    return [
      'intro' => [
        '#markup' => $this->t('These are the Drupal components owned by @module. Drupal builds the complete component inventory first, then this page filters and displays @count rows at a time. Select a component to inspect its relationships, related PHP structure, source evidence, and AI context.', [
          '@module' => $architecture->module->label,
          '@count' => self::COMPONENTS_PER_PAGE,
        ]),
      ],
      'filter' => $this->componentFormBuilder->getForm(
        $this->filterForm,
        'drupal_developer_assistant.module_components',
        $filter,
        ['module_id' => $module_id],
      ),
      'summary' => [
        '#markup' => $this->formatPlural(
          count($components),
          '1 component found.',
          '@count components found.',
        ),
      ],
      'component_summary' => [
        '#type' => 'table',
        '#caption' => $this->t('Component summary'),
        '#header' => [
          $this->t('Component type'),
          $this->t('Count'),
        ],
        '#rows' => $summary_rows,
        '#empty' => $this->t('No owned components were discovered.'),
      ],
      'components' => [
        '#type' => 'table',
        '#caption' => $this->t('Owned components'),
        '#header' => [
          $this->t('Type'),
          $this->t('Identifier'),
          $this->t('Label'),
          $this->t('Source path'),
        ],
        '#rows' => $component_rows,
        '#empty' => $this->t('No owned components match the filter.'),
      ],
      'pager' => ['#type' => 'pager'],
    ];
  }

  /**
   * Builds the paginated source-file page for one enabled module.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param string $module_id
   *   The module machine name from the route.
   *
   * @return array
   *   A render array containing filtered source-file metadata.
   */
  public function moduleSourceFiles(
    Request $request,
    string $module_id,
  ): array {
    $module = $this->enabledModule($module_id);
    if ($module === NULL) {
      throw new NotFoundHttpException(sprintf('Enabled module "%s" was not found.', $module_id));
    }

    $filter = trim($request->query->getString('filter'));
    $source_files = array_values(array_filter(
      $this->sourceFileCollector->collect($module),
      fn($source_file): bool => $this->matchesFilter($filter, [
        $source_file->relativePath,
        $source_file->fileType,
        $source_file->category,
        (string) $source_file->size,
      ]),
    ));

    $pager = $this->pagerManager->createPager(
      count($source_files),
      self::SOURCE_FILES_PER_PAGE,
    );
    $page_files = array_slice(
      $source_files,
      $pager->getCurrentPage() * self::SOURCE_FILES_PER_PAGE,
      self::SOURCE_FILES_PER_PAGE,
    );
    $source_file_rows = [];
    foreach ($page_files as $source_file) {
      $source_file_rows[] = [
        $source_file->relativePath,
        $source_file->fileType,
        $source_file->category,
        $source_file->size,
      ];
    }

    return [
      'intro' => [
        '#markup' => $this->t('Browse file metadata for @module. The collector reads paths, types, categories, and sizes without displaying file contents.', [
          '@module' => $module->label,
        ]),
      ],
      'filter' => $this->componentFormBuilder->getForm(
        $this->filterForm,
        'drupal_developer_assistant.module_source_files',
        $filter,
        ['module_id' => $module_id],
      ),
      'summary' => [
        '#markup' => $this->formatPlural(
          count($source_files),
          '1 source file found.',
          '@count source files found.',
        ),
      ],
      'source_files' => [
        '#type' => 'table',
        '#caption' => $this->t('Source files'),
        '#header' => [
          $this->t('Path'),
          $this->t('File type'),
          $this->t('Category'),
          $this->t('Size (bytes)'),
        ],
        '#rows' => $source_file_rows,
        '#empty' => $this->t('No source files match the filter.'),
      ],
      'pager' => ['#type' => 'pager'],
    ];
  }

  /**
   * Builds the paginated PHP-structure page for one enabled module.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param string $module_id
   *   The module machine name from the route.
   *
   * @return array
   *   A render array containing PHP structure for the current page.
   */
  public function modulePhpStructure(
    Request $request,
    string $module_id,
  ): array {
    $module = $this->enabledModule($module_id);
    if ($module === NULL) {
      throw new NotFoundHttpException(sprintf('Enabled module "%s" was not found.', $module_id));
    }

    $filter = trim($request->query->getString('filter'));
    $php_source_files = array_values(array_filter(
      $this->sourceFileCollector->collect($module),
      fn($source_file): bool => $source_file->fileType === 'PHP'
        && $this->matchesFilter($filter, [
          $source_file->relativePath,
          $source_file->category,
        ]),
    ));

    $pager = $this->pagerManager->createPager(
      count($php_source_files),
      self::PHP_FILES_PER_PAGE,
    );
    $page_source_files = array_slice(
      $php_source_files,
      $pager->getCurrentPage() * self::PHP_FILES_PER_PAGE,
      self::PHP_FILES_PER_PAGE,
    );

    $php_file_rows = [];
    foreach ($page_source_files as $source_file) {
      $php_file = $this->phpSourceAnalyzer->analyze($module, $source_file);
      $import_items = [];
      foreach ($php_file->imports as $alias => $name) {
        $import_items[] = $alias . ': ' . $name;
      }
      $php_file_rows[] = [
        $php_file->sourceFile->relativePath,
        implode(', ', $php_file->namespaces),
        [
          'data' => [
            '#theme' => 'item_list',
            '#items' => $import_items,
          ],
        ],
        [
          'data' => [
            '#theme' => 'item_list',
            '#items' => array_map(
              $this->phpSymbolSummary(...),
              $php_file->symbols,
            ),
          ],
        ],
        [
          'data' => [
            '#theme' => 'item_list',
            '#items' => array_map(
              $this->phpFunctionSignature(...),
              $php_file->functions,
            ),
          ],
        ],
        $php_file->error ?? '',
      ];
    }

    return [
      'intro' => [
        '#markup' => $this->t('Filter PHP file paths for @module. Only the @count files on the current page are parsed, and the analyzer never loads or executes the module classes.', [
          '@module' => $module->label,
          '@count' => self::PHP_FILES_PER_PAGE,
        ]),
      ],
      'filter' => $this->componentFormBuilder->getForm(
        $this->filterForm,
        'drupal_developer_assistant.module_php_structure',
        $filter,
        ['module_id' => $module_id],
      ),
      'summary' => [
        '#markup' => $this->formatPlural(
          count($php_source_files),
          '1 PHP file found.',
          '@count PHP files found.',
        ),
      ],
      'php_structure' => [
        '#type' => 'table',
        '#caption' => $this->t('PHP structure'),
        '#header' => [
          $this->t('Path'),
          $this->t('Namespace'),
          $this->t('Imports'),
          $this->t('Symbols'),
          $this->t('Functions'),
          $this->t('Analysis error'),
        ],
        '#rows' => $php_file_rows,
        '#empty' => $this->t('No PHP files match the filter.'),
      ],
      'pager' => ['#type' => 'pager'],
    ];
  }

  /**
   * Builds the filtered relationship report for one enabled module.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param string $module_id
   *   The module machine name from the route.
   *
   * @return array
   *   A render array for the module relationships page.
   */
  public function moduleRelationships(
    Request $request,
    string $module_id,
  ): array {
    $architecture = $this->moduleArchitectureBuilder->build($module_id);
    if ($architecture === NULL) {
      throw new NotFoundHttpException(sprintf('Enabled module "%s" was not found.', $module_id));
    }

    $filter = trim($request->query->getString('filter'));
    $relationships = array_values(array_filter(
      $architecture->relationships,
      fn(ComponentRelationship $relationship): bool => $this->matchesFilter(
        $filter,
        [
          $relationship->sourceType,
          $relationship->sourceId,
          $relationship->relationship,
          $relationship->targetType,
          $relationship->targetId,
          ...array_keys($relationship->metadata),
          ...array_values($relationship->metadata),
        ],
      ),
    ));
    $pager = $this->pagerManager->createPager(
      count($relationships),
      self::RELATIONSHIPS_PER_PAGE,
    );
    $page_relationships = array_slice(
      $relationships,
      $pager->getCurrentPage() * self::RELATIONSHIPS_PER_PAGE,
      self::RELATIONSHIPS_PER_PAGE,
    );
    $relationship_rows = array_map(
      $this->relationshipRow(...),
      $page_relationships,
    );

    return [
      'back' => [
        '#type' => 'link',
        '#title' => $this->t('Back to module overview'),
        '#url' => Url::fromRoute(
          'drupal_developer_assistant.module_overview',
          ['module_id' => $module_id],
        ),
      ],
      'context_preview' => [
        '#type' => 'link',
        '#title' => $this->t('Preview AI context'),
        '#url' => Url::fromRoute(
          'drupal_developer_assistant.module_context',
          ['module_id' => $module_id],
        ),
      ],
      'intro' => [
        '#markup' => $this->t('Browse relationships discovered for @module. Drupal collects the complete relationship graph first, then this page filters and displays @count rows at a time.', [
          '@module' => $architecture->module->label,
          '@count' => self::RELATIONSHIPS_PER_PAGE,
        ]),
      ],
      'filter' => $this->componentFormBuilder->getForm(
        $this->filterForm,
        'drupal_developer_assistant.module_relationships',
        $filter,
        ['module_id' => $module_id],
      ),
      'summary' => [
        '#markup' => $this->formatPlural(
          count($relationships),
          '1 relationship found.',
          '@count relationships found.',
        ),
      ],
      'relationships' => [
        '#type' => 'table',
        '#caption' => $this->t('Module relationships'),
        '#header' => [
          $this->t('Source'),
          $this->t('Relationship'),
          $this->t('Target'),
          $this->t('Metadata'),
        ],
        '#rows' => $relationship_rows,
        '#empty' => $this->t('No module relationships match the filter.'),
      ],
      'pager' => ['#type' => 'pager'],
    ];
  }

  /**
   * Builds the detail page for one discovered module component.
   *
   * @param string $module_id
   *   The machine name of the component's module.
   * @param string $component_reference
   *   Opaque reference generated from the component type and identifier.
   *
   * @return array
   *   A render array for the component detail page.
   */
  public function moduleComponent(
    string $module_id,
    string $component_reference,
  ): array {
    $details = $this->componentDetailsResolver->resolve(
      $module_id,
      $component_reference,
    );
    if ($details === NULL) {
      throw new NotFoundHttpException('The requested module component was not found.');
    }

    $php_rows = [];
    foreach ($details->phpFiles as $php_file) {
      $php_rows[] = [
        $php_file->sourceFile->relativePath,
        implode(', ', $php_file->namespaces),
        [
          'data' => [
            '#theme' => 'item_list',
            '#items' => array_map(
              $this->phpSymbolSummary(...),
              $php_file->symbols,
            ),
          ],
        ],
        [
          'data' => [
            '#theme' => 'item_list',
            '#items' => array_map(
              $this->phpFunctionSignature(...),
              $php_file->functions,
            ),
          ],
        ],
        $php_file->error ?? '',
      ];
    }

    return [
      'back' => [
        '#type' => 'link',
        '#title' => $this->t('Back to module components'),
        '#url' => Url::fromRoute(
          'drupal_developer_assistant.module_components',
          ['module_id' => $module_id],
        ),
      ],
      'component' => [
        '#type' => 'table',
        '#caption' => $this->t('Component'),
        '#header' => [
          $this->t('Module'),
          $this->t('Type'),
          $this->t('Identifier'),
          $this->t('Label'),
          $this->t('Source path'),
        ],
        '#rows' => [
          [
            $details->module->label,
            $details->component->type,
            $details->component->id,
            $details->component->label,
            $details->component->sourcePath ?? '',
          ],
        ],
      ],
      'agent' => [
        '#type' => 'link',
        '#title' => $this->t('Ask the AI Assistant about this component'),
        '#url' => $this->newAgentConversationUrl(),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
      'outgoing_relationships' => [
        '#type' => 'table',
        '#caption' => $this->t('Outgoing relationships'),
        '#header' => [
          $this->t('Source'),
          $this->t('Relationship'),
          $this->t('Target'),
          $this->t('Metadata'),
        ],
        '#rows' => array_map(
          $this->relationshipRow(...),
          $details->outgoingRelationships,
        ),
        '#empty' => $this->t('No outgoing relationships were discovered.'),
      ],
      'incoming_relationships' => [
        '#type' => 'table',
        '#caption' => $this->t('Incoming relationships'),
        '#header' => [
          $this->t('Source'),
          $this->t('Relationship'),
          $this->t('Target'),
          $this->t('Metadata'),
        ],
        '#rows' => array_map(
          $this->relationshipRow(...),
          $details->incomingRelationships,
        ),
        '#empty' => $this->t('No incoming relationships were discovered.'),
      ],
      'php_structure' => [
        '#type' => 'table',
        '#caption' => $this->t('Related PHP structure'),
        '#header' => [
          $this->t('Path'),
          $this->t('Namespace'),
          $this->t('Symbols'),
          $this->t('Functions'),
          $this->t('Analysis error'),
        ],
        '#rows' => $php_rows,
        '#empty' => $this->t('No PHP structure is associated with this component.'),
      ],
    ];
  }

  /**
   * Finds one enabled module collected by the inspector.
   */
  private function enabledModule(string $module_id): ?ModuleComponent {
    foreach ($this->collectorManager->get('modules')->collect() as $module) {
      if (!$module instanceof ModuleComponent) {
        throw new \UnexpectedValueException('The modules collector must return ModuleComponent objects.');
      }
      if ($module->id === $module_id) {
        return $module;
      }
    }

    return NULL;
  }

  /**
   * Builds a render-table row for one component relationship.
   */
  private function relationshipRow(
    ComponentRelationship $relationship,
  ): array {
    $metadata_items = [];
    foreach ($relationship->metadata as $name => $value) {
      $metadata_items[] = $name . ': ' . $value;
    }

    return [
      $relationship->sourceType . ': ' . $relationship->sourceId,
      $relationship->relationship,
      $relationship->targetType . ': ' . $relationship->targetId,
      [
        'data' => [
          '#theme' => 'item_list',
          '#items' => $metadata_items,
        ],
      ],
    ];
  }

  /**
   * Formats a class-like PHP symbol without including source contents.
   */
  private function phpSymbolSummary(PhpSymbol $symbol): string {
    $declaration = implode(' ', [
      ...$symbol->modifiers,
      $symbol->kind,
      $symbol->fullyQualifiedName,
    ]);
    $details = [$declaration];
    if ($symbol->extends !== []) {
      $details[] = 'extends ' . implode(', ', $symbol->extends);
    }
    if ($symbol->implements !== []) {
      $details[] = 'implements ' . implode(', ', $symbol->implements);
    }
    if ($symbol->traits !== []) {
      $details[] = 'traits: ' . implode(', ', $symbol->traits);
    }
    if ($symbol->attributes !== []) {
      $details[] = 'attributes: ' . implode(', ', $symbol->attributes);
    }
    if ($symbol->properties !== []) {
      $details[] = 'properties: ' . implode(', ', array_map(
        $this->phpPropertySignature(...),
        $symbol->properties,
      ));
    }
    if ($symbol->methods !== []) {
      $details[] = 'methods: ' . implode(', ', array_map(
        $this->phpMethodSignature(...),
        $symbol->methods,
      ));
    }

    return implode(' | ', $details);
  }

  /**
   * Formats a PHP property declaration from structural metadata.
   */
  private function phpPropertySignature(PhpProperty $property): string {
    return implode(' ', array_filter([
      $property->visibility,
      $property->static ? 'static' : NULL,
      $property->readonly ? 'readonly' : NULL,
      $property->type,
      '$' . $property->name,
    ]));
  }

  /**
   * Formats a PHP method declaration from structural metadata.
   */
  private function phpMethodSignature(PhpMethod $method): string {
    $modifiers = array_filter([
      $method->visibility,
      $method->abstract ? 'abstract' : NULL,
      $method->final ? 'final' : NULL,
      $method->static ? 'static' : NULL,
    ]);
    $name = ($method->returnsByReference ? '&' : '')
      . $method->name
      . '('
      . implode(', ', array_map(
        $this->phpParameterSignature(...),
        $method->parameters,
      ))
      . ')';
    if ($method->returnType !== NULL) {
      $name .= ': ' . $method->returnType;
    }

    return implode(' ', [...$modifiers, $name]);
  }

  /**
   * Formats a named PHP function declaration from structural metadata.
   */
  private function phpFunctionSignature(PhpFunction $function): string {
    $signature = 'function '
      . ($function->returnsByReference ? '&' : '')
      . $function->fullyQualifiedName
      . '('
      . implode(', ', array_map(
        $this->phpParameterSignature(...),
        $function->parameters,
      ))
      . ')';
    if ($function->returnType !== NULL) {
      $signature .= ': ' . $function->returnType;
    }

    return $signature;
  }

  /**
   * Formats a PHP parameter without exposing its default value.
   */
  private function phpParameterSignature(PhpParameter $parameter): string {
    return implode('', [
      $parameter->promoted ? 'promoted ' : '',
      $parameter->type === NULL ? '' : $parameter->type . ' ',
      $parameter->byReference ? '&' : '',
      $parameter->variadic ? '...' : '',
      '$' . $parameter->name,
      $parameter->hasDefault ? ' = …' : '',
    ]);
  }

  /**
   * Builds a link that starts a clean Developer Assistant conversation.
   */
  private function newAgentConversationUrl(): Url {
    return Url::fromRoute(
      'ai_agents_explorer.explorer',
      [],
      [
        'query' => [
          'agent_id' => 'drupal_developer_assistant',
          'drupal_developer_assistant_new' => 1,
        ],
      ],
    );
  }

  /**
   * Determines whether any searchable value contains the filter text.
   *
   * @param string $filter
   *   The normalized filter text.
   * @param list<string> $values
   *   Values from a component record that can be searched.
   */
  private function matchesFilter(string $filter, array $values): bool {
    if ($filter === '') {
      return TRUE;
    }

    foreach ($values as $value) {
      if (stripos($value, $filter) !== FALSE) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
