<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the inspector pages and filters.
 */
#[Group('drupal_developer_assistant')]
#[RunTestsInSeparateProcesses]
final class InspectorPageTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['drupal_developer_assistant'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests access to and content of the overview page.
   */
  public function testOverviewPage(): void {
    $this->drupalGet('/admin/reports/drupal-developer-assistant');
    $this->assertSession()->statusCodeEquals(403);

    $this->loginInspectorUser();

    $this->drupalGet('/admin/reports/drupal-developer-assistant');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists(
      'css',
      '.drupal-developer-assistant-overview',
    );
    $this->assertSession()->pageTextContains('Choose what you want to do.');
    $this->assertSession()->linkExists('Ask the AI Assistant');
    $this->assertSession()->linkExists('Inspect and analyze a module');
    $this->assertSession()->linkExists('Browse the Drupal catalog');
    $this->assertSession()->linkByHrefExists(
      '/admin/config/ai/agents/explore?agent_id=drupal_developer_assistant&drupal_developer_assistant_new=1',
    );
    $this->assertSession()->linkByHrefExists(
      '/admin/reports/drupal-developer-assistant/modules',
    );
    $this->assertSession()->linkByHrefExists(
      '/admin/reports/drupal-developer-assistant/catalog',
    );
    $this->assertSession()->elementNotExists('css', 'table');
  }

  /**
   * Tests the read-only component catalog landing page.
   */
  public function testCatalogPage(): void {
    $this->loginInspectorUser();

    $this->drupalGet('/admin/reports/drupal-developer-assistant/catalog');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains(
      'These pages are read-only indexes of Drupal data.',
    );
    foreach ([
      'services',
      'plugins',
      'entities',
      'routes',
      'controllers',
      'forms',
      'hooks',
      'configuration',
      'architecture',
    ] as $section) {
      $this->assertSession()->linkByHrefExists(
        '/admin/reports/drupal-developer-assistant/' . $section,
      );
    }
    $this->assertSession()->pageTextContains(
      'Use Modules when you want the complete inspection and AI-analysis workflow.',
    );
  }

  /**
   * Tests the enabled-modules page and its filter.
   */
  public function testModulesPage(): void {
    $this->loginInspectorUser();

    $path = '/admin/reports/drupal-developer-assistant/modules';
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('This is the main workflow.');
    $this->assertSession()->linkExists('Inspect and analyze');
    $this->assertSession()->fieldExists('filter');
    $this->assertSession()->elementTextContains('css', 'table caption', 'Enabled modules');
    $this->assertSession()->elementTextContains('css', 'table', 'Package');
    $this->assertSession()->elementTextContains('css', 'table', 'Version');
    $this->assertSession()->elementTextContains('css', 'table', 'Dependencies');
    $this->assertSession()->elementTextContains('css', 'table', 'Drupal Developer Assistant');
    $this->assertSession()->elementTextContains('css', 'table', 'drupal_developer_assistant');
    $this->assertSession()->elementTextContains('css', 'table', 'Development');
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'modules/custom/drupal_developer_assistant',
    );
    $this->assertSession()->linkByHrefExists(
      '/admin/reports/drupal-developer-assistant/modules/drupal_developer_assistant',
    );

    $this->drupalGet($path, ['query' => ['filter' => 'Development']]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals('filter', 'Development');
    $this->assertSession()->pageTextContains('1 module found.');
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'drupal_developer_assistant',
    );
    $this->assertSession()->elementTextNotContains(
      'css',
      'table',
      'core/modules/system',
    );
    $this->assertSession()->linkExists('Clear');
  }

  /**
   * Tests the compact module overview and its access control.
   */
  public function testModuleOverviewPage(): void {
    $path = '/admin/reports/drupal-developer-assistant/modules/drupal_developer_assistant';
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(403);

    $this->loginInspectorUser();
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Module overview');
    $this->assertSession()->pageTextContains(
      'This compact overview is the starting point',
    );
    $this->assertSession()->elementTextContains(
      'xpath',
      '//table[caption[normalize-space(.)="Module"]]',
      'Drupal Developer Assistant',
    );
    $inventory_table = '//table[caption[normalize-space(.)="Inventory summary"]]';
    $this->assertSession()->elementTextContains(
      'xpath',
      $inventory_table,
      'All architecture components',
    );
    $this->assertSession()->elementTextContains(
      'xpath',
      $inventory_table,
      'Source files',
    );
    $this->assertSession()->elementTextContains(
      'xpath',
      $inventory_table,
      'PHP files',
    );
    $this->assertSession()->elementTextContains(
      'xpath',
      $inventory_table,
      'Relationships',
    );
    $this->assertSession()->linkByHrefExists(
      '/admin/reports/drupal-developer-assistant/modules/drupal_developer_assistant/components',
    );
    $this->assertSession()->linkByHrefExists(
      '/admin/reports/drupal-developer-assistant/modules/drupal_developer_assistant/files',
    );
    $this->assertSession()->linkByHrefExists(
      '/admin/reports/drupal-developer-assistant/modules/drupal_developer_assistant/php',
    );
    $this->assertSession()->linkByHrefExists(
      '/admin/reports/drupal-developer-assistant/modules/drupal_developer_assistant/relationships',
    );
    $this->assertSession()->linkByHrefExists(
      '/admin/reports/drupal-developer-assistant/modules/drupal_developer_assistant/context',
    );
    $this->assertSession()->linkExists('Preview AI context');
    $this->assertSession()->elementNotExists(
      'xpath',
      '//table[caption[normalize-space(.)="Owned components"]]',
    );
    $this->assertSession()->elementNotExists(
      'xpath',
      '//table[caption[normalize-space(.)="PHP structure"]]',
    );
    $this->assertSession()->buttonExists('Refresh architecture');
    $this->submitForm([], 'Refresh architecture');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->addressEquals($path);
    $this->assertSession()->pageTextContains(
      'The architecture cache for drupal_developer_assistant was refreshed.',
    );

    $this->drupalGet(
      '/admin/reports/drupal-developer-assistant/modules/not_enabled',
    );
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests the services page and its filter.
   */
  public function testServicesPage(): void {
    $this->loginInspectorUser();

    $path = '/admin/reports/drupal-developer-assistant/services';
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('filter');

    $service_table = '//table[caption[contains(normalize-space(.), "Services")]]';
    $this->assertSession()->elementTextContains(
      'xpath',
      $service_table,
      'Service ID',
    );
    $this->assertSession()->elementTextContains(
      'xpath',
      $service_table,
      'Referenced services',
    );
    $this->assertSession()->elementTextContains(
      'xpath',
      $service_table,
      'Drupal\drupal_developer_assistant\Collector\CollectorManagerInterface',
    );
    $this->assertSession()->elementTextContains(
      'xpath',
      $service_table,
      'Drupal\drupal_developer_assistant\Collector\CollectorManager',
    );

    $this->drupalGet($path, [
      'query' => ['filter' => 'CollectorManagerInterface'],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals(
      'filter',
      'CollectorManagerInterface',
    );
    $this->assertSession()->pageTextContains('1 service found.');
    $this->assertSession()->elementTextContains(
      'xpath',
      $service_table,
      'Drupal\drupal_developer_assistant\Collector\CollectorManagerInterface',
    );
    $this->assertSession()->elementTextNotContains(
      'xpath',
      $service_table,
      'module_handler',
    );
    $this->assertSession()->linkExists('Clear');
  }

  /**
   * Tests the plugin-types page and its filter.
   */
  public function testPluginsPage(): void {
    $this->loginInspectorUser();

    $path = '/admin/reports/drupal-developer-assistant/plugins';
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('filter');
    $this->assertSession()->elementTextContains(
      'css',
      'table caption',
      'Plugin types and managers',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'plugin.manager.block',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'Drupal\Core\Block\BlockManager',
    );
    $this->assertSession()->linkByHrefExists(
      '/admin/reports/drupal-developer-assistant/plugins/block',
    );
    $this->assertSession()->linkByHrefExists(
      '/admin/reports/drupal-developer-assistant/plugins/action',
    );

    $this->drupalGet($path, [
      'query' => ['filter' => 'plugin.manager.block'],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals(
      'filter',
      'plugin.manager.block',
    );
    $this->assertSession()->pageTextContains('1 plugin type found.');
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'plugin.manager.block',
    );
    $this->assertSession()->elementTextNotContains(
      'css',
      'table',
      'plugin.manager.action',
    );
    $this->assertSession()->linkExists('Clear');
  }

  /**
   * Tests the Block plugins page and its filter.
   */
  public function testBlockPluginsPage(): void {
    $this->loginInspectorUser();

    $path = '/admin/reports/drupal-developer-assistant/plugins/block';
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('filter');
    $this->assertSession()->elementTextContains(
      'css',
      'table caption',
      'Block plugins',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'system_powered_by_block',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'Drupal\system\Plugin\Block\SystemPoweredByBlock',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'core/modules/system/src/Plugin/Block/SystemPoweredByBlock.php',
    );

    $this->drupalGet($path, [
      'query' => ['filter' => 'system_powered_by_block'],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals(
      'filter',
      'system_powered_by_block',
    );
    $this->assertSession()->pageTextContains('1 Block plugin found.');
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'system_powered_by_block',
    );
    $this->assertSession()->elementTextNotContains(
      'css',
      'table',
      'system_branding_block',
    );
    $this->assertSession()->linkExists('Clear');
  }

  /**
   * Tests the Action plugins page and its filter.
   */
  public function testActionPluginsPage(): void {
    $this->loginInspectorUser();

    $path = '/admin/reports/drupal-developer-assistant/plugins/action';
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('filter');
    $this->assertSession()->elementTextContains(
      'css',
      'table caption',
      'Action plugins',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'action_goto_action',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'Drupal\Core\Action\Plugin\Action\GotoAction',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'core/lib/Drupal/Core/Action/Plugin/Action/GotoAction.php',
    );

    $this->drupalGet($path, [
      'query' => ['filter' => 'action_goto_action'],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals(
      'filter',
      'action_goto_action',
    );
    $this->assertSession()->pageTextContains('1 Action plugin found.');
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'action_goto_action',
    );
    $this->assertSession()->elementTextNotContains(
      'css',
      'table',
      'action_message_action',
    );
    $this->assertSession()->linkExists('Clear');
  }

  /**
   * Tests the entity-types page and its filter.
   */
  public function testEntityTypesPage(): void {
    $this->loginInspectorUser();

    $path = '/admin/reports/drupal-developer-assistant/entities';
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('filter');
    $this->assertSession()->elementTextContains(
      'css',
      'table caption',
      'Entity types',
    );
    $this->assertSession()->elementTextContains('css', 'table', 'user');
    $this->assertSession()->elementTextContains('css', 'table', 'content');
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'Drupal\user\Entity\User',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'form.default: Drupal\user\ProfileForm',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'core/modules/user/src/Entity/User.php',
    );

    $this->drupalGet($path, ['query' => ['filter' => 'users']]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals('filter', 'users');
    $this->assertSession()->pageTextContains('1 entity type found.');
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'Drupal\user\Entity\User',
    );
    $this->assertSession()->elementTextNotContains(
      'css',
      'table',
      'user_role',
    );
    $this->assertSession()->linkExists('Clear');
  }

  /**
   * Tests the routes page and its filter.
   */
  public function testRoutesPage(): void {
    $this->loginInspectorUser();

    $path = '/admin/reports/drupal-developer-assistant/routes';
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('filter');
    $this->assertSession()->elementTextContains(
      'css',
      'table caption',
      'Routes',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'drupal_developer_assistant.entities',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'Drupal\drupal_developer_assistant\Controller\InspectorController',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      '_permission: access drupal developer assistant',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'modules/custom/drupal_developer_assistant/src/Controller/InspectorController.php',
    );

    $this->drupalGet($path, [
      'query' => ['filter' => 'drupal_developer_assistant.entities'],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals(
      'filter',
      'drupal_developer_assistant.entities',
    );
    $this->assertSession()->pageTextContains('1 route found.');
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'drupal_developer_assistant.entities',
    );
    $this->assertSession()->elementTextNotContains(
      'css',
      'table',
      'drupal_developer_assistant.services',
    );
    $this->assertSession()->linkExists('Clear');
  }

  /**
   * Tests the controllers page and its filter.
   */
  public function testControllersPage(): void {
    $this->loginInspectorUser();

    $path = '/admin/reports/drupal-developer-assistant/controllers';
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('filter');
    $this->assertSession()->elementTextContains(
      'css',
      'table caption',
      'Controllers',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'InspectorController',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'drupal_developer_assistant.controllers',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'controllers()',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'modules/custom/drupal_developer_assistant/src/Controller/InspectorController.php',
    );

    $this->drupalGet($path, ['query' => ['filter' => 'InspectorController']]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals('filter', 'InspectorController');
    $this->assertSession()->pageTextContains('1 controller found.');
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'Drupal\drupal_developer_assistant\Controller\InspectorController',
    );
    $this->assertSession()->linkExists('Clear');
  }

  /**
   * Tests the forms page and its filter.
   */
  public function testFormsPage(): void {
    $this->loginInspectorUser();

    $path = '/admin/reports/drupal-developer-assistant/forms';
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('filter');
    $this->assertSession()->elementTextContains(
      'css',
      'table caption',
      'Forms',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'UserLoginForm',
    );
    $this->assertSession()->elementTextContains('css', 'table', 'user.login');
    $this->assertSession()->elementTextContains('css', 'table', '/user/login');
    $this->assertSession()->elementTextContains('css', 'table', 'form');
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'core/modules/user/src/Form/UserLoginForm.php',
    );

    $this->drupalGet($path, ['query' => ['filter' => 'UserLoginForm']]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals('filter', 'UserLoginForm');
    $this->assertSession()->pageTextContains('1 form found.');
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'Drupal\user\Form\UserLoginForm',
    );
    $this->assertSession()->linkExists('Clear');
  }

  /**
   * Tests the hook-implementations page and its filter.
   */
  public function testHooksPage(): void {
    $this->loginInspectorUser();

    $path = '/admin/reports/drupal-developer-assistant/hooks';
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('filter');
    $this->assertSession()->elementTextContains(
      'css',
      'table caption',
      'Hook implementations',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'hook_hook_info()',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'system_hook_info',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'procedural',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'core/modules/system/system.module',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'Drupal\system\Hook\SystemHooks::help',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'object-oriented',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'core/modules/system/src/Hook/SystemHooks.php',
    );

    $this->drupalGet($path, ['query' => ['filter' => 'system_hook_info']]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals('filter', 'system_hook_info');
    $this->assertSession()->pageTextContains('1 hook implementation found.');
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'system_hook_info',
    );
    $this->assertSession()->elementTextNotContains(
      'css',
      'table',
      'Drupal\system\Hook\SystemHooks::help',
    );
    $this->assertSession()->linkExists('Clear');
  }

  /**
   * Tests the active-configuration page and its filter.
   */
  public function testConfigurationPage(): void {
    $this->loginInspectorUser();

    $path = '/admin/reports/drupal-developer-assistant/configuration';
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('filter');
    $this->assertSession()->elementTextContains(
      'css',
      'table caption',
      'Active configuration',
    );
    $this->assertSession()->elementTextContains('css', 'table', 'system.site');
    $this->assertSession()->elementTextContains('css', 'table', 'simple');
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'user.role.authenticated',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'configuration_entity',
    );
    $this->assertSession()->elementTextContains('css', 'table', 'user_role');
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'Drupal\user\Entity\Role',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'core/modules/user/src/Entity/Role.php',
    );

    $this->drupalGet($path, [
      'query' => ['filter' => 'user.role.authenticated'],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals(
      'filter',
      'user.role.authenticated',
    );
    $this->assertSession()->pageTextContains('1 configuration object found.');
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'user.role.authenticated',
    );
    $this->assertSession()->elementTextNotContains(
      'css',
      'table',
      'system.site',
    );
    $this->assertSession()->linkExists('Clear');
  }

  /**
   * Tests the component-relationships page and its filter.
   */
  public function testArchitecturePage(): void {
    $this->loginInspectorUser();

    $path = '/admin/reports/drupal-developer-assistant/architecture';
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('filter');
    $this->assertSession()->elementTextContains(
      'css',
      'table caption',
      'Component relationships',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'route: drupal_developer_assistant.controllers',
    );
    $this->assertSession()->elementTextContains('css', 'table', 'invokes');
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'controller: Drupal\drupal_developer_assistant\Controller\InspectorController',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'method: controllers',
    );
    $this->assertSession()->elementTextContains('css', 'table', 'requires');
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'permission: access drupal developer assistant',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'entity_type: user',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'module: user',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'entity_class: Drupal\user\Entity\User',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'handler: storage',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'module: user',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'depends_on',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'declaration: drupal:system',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'plugin: block:system_powered_by_block',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'hook_implementation: hook_info:system_hook_info',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'configuration: user.role.authenticated',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'provided_by',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'configuration: core.entity_view_mode.user.compact',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'dependency_type: module',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'service: Drupal\drupal_developer_assistant\Controller\InspectorController',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'implemented_by',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'context: constructor',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'navigates_to',
    );
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'navigation_type: local_task',
    );

    $this->drupalGet($path, [
      'query' => ['filter' => 'drupal_developer_assistant.controllers'],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals(
      'filter',
      'drupal_developer_assistant.controllers',
    );
    $this->assertSession()->pageTextContains('relationships found.');
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'route: drupal_developer_assistant.controllers',
    );
    $this->assertSession()->elementTextNotContains(
      'css',
      'table',
      'route: drupal_developer_assistant.services',
    );
    $this->assertSession()->linkExists('Clear');

    $this->drupalGet($path, [
      'query' => ['filter' => 'Drupal\user\Entity\User'],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('2 relationships found.');
    $this->assertSession()->elementTextContains(
      'css',
      'table',
      'entity_class: Drupal\user\Entity\User',
    );
    $this->assertSession()->elementTextNotContains(
      'css',
      'table',
      'entity_class: Drupal\user\Entity\Role',
    );
  }

  /**
   * Tests filtering and pagination on the module source-files page.
   */
  public function testModuleSourceFilesPage(): void {
    $path = '/admin/reports/drupal-developer-assistant/modules/drupal_developer_assistant/files';
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(403);

    $this->loginInspectorUser();
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Source files');
    $this->assertSession()->pageTextContains(
      'without displaying file contents',
    );
    $this->assertSession()->fieldExists('filter');
    $source_file_table = '//table[caption[normalize-space(.)="Source files"]]';
    $this->assertSession()->elementTextContains(
      'xpath',
      $source_file_table,
      'drupal_developer_assistant.info.yml',
    );
    $this->assertSession()->elementExists('css', 'nav.pager');

    $this->drupalGet($path, [
      'query' => ['filter' => 'drupal_developer_assistant.info.yml'],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals(
      'filter',
      'drupal_developer_assistant.info.yml',
    );
    $this->assertSession()->pageTextContains('1 source file found.');
    $this->assertSession()->elementTextContains(
      'xpath',
      $source_file_table,
      'YAML',
    );
    $this->assertSession()->elementTextContains(
      'xpath',
      $source_file_table,
      'Configuration',
    );
    $this->assertSession()->linkByHrefExists($path);
    $this->assertSession()->elementNotExists('css', 'nav.pager');

    $this->drupalGet(
      '/admin/reports/drupal-developer-assistant/modules/not_enabled/files',
    );
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests filtering, pagination, and details on module components.
   */
  public function testModuleComponentsPage(): void {
    $path = '/admin/reports/drupal-developer-assistant/modules/drupal_developer_assistant/components';
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(403);

    $this->loginInspectorUser();
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Module components');
    $this->assertSession()->pageTextContains(
      'These are the Drupal components owned by Drupal Developer Assistant.',
    );
    $this->assertSession()->pageTextContains(
      'displays 25 rows at a time',
    );
    $this->assertSession()->fieldExists('filter');
    $component_table = '//table[caption[normalize-space(.)="Owned components"]]';
    $this->assertSession()->elementsCount(
      'xpath',
      $component_table . '/tbody/tr',
      25,
    );
    $this->assertSession()->elementExists('css', 'nav.pager');

    $this->drupalGet($path, [
      'query' => ['filter' => 'drupal_developer_assistant.overview'],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals(
      'filter',
      'drupal_developer_assistant.overview',
    );
    $this->assertSession()->elementTextContains(
      'xpath',
      $component_table,
      'route',
    );
    $this->assertSession()->elementTextContains(
      'xpath',
      $component_table,
      'drupal_developer_assistant.overview',
    );
    $this->assertSession()->linkByHrefExists($path);
    $this->assertSession()->elementNotExists('css', 'nav.pager');
    $component_link = $this->getSession()->getPage()->find(
      'xpath',
      $component_table . '//tr[td[1][normalize-space(.)="route"]]//a[normalize-space(.)="drupal_developer_assistant.overview"]',
    );
    $this->assertNotNull($component_link);
    $this->assertMatchesRegularExpression(
      '@/components/[a-f0-9]{64}$@',
      $component_link->getAttribute('href'),
    );

    $component_path = $component_link->getAttribute('href');
    $this->assertNotNull($component_path);
    $this->assertStringContainsString(
      '/drupal-developer-assistant/modules/',
      $component_path,
    );
    $this->drupalGet($component_path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Component details');
    $this->assertSession()->elementTextContains(
      'xpath',
      '//table[caption[normalize-space(.)="Component"]]',
      'drupal_developer_assistant.overview',
    );
    $this->assertSession()->pageTextContains('Outgoing relationships');
    $this->assertSession()->pageTextContains('Incoming relationships');
    $this->assertSession()->pageTextContains('Related PHP structure');
    $this->assertSession()->pageTextContains('invokes');
    $this->assertSession()->pageTextContains('InspectorController');
    $this->assertSession()->linkExists('Back to module components');
    $this->assertSession()->linkExists(
      'Ask the AI Assistant about this component',
    );
    $this->assertSession()->linkByHrefExists(
      '/admin/config/ai/agents/explore?agent_id=drupal_developer_assistant&drupal_developer_assistant_new=1',
    );

    $this->drupalGet(
      '/admin/reports/drupal-developer-assistant/modules/not_enabled/components',
    );
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests lazy structural analysis and pagination of module PHP files.
   */
  public function testModulePhpStructurePage(): void {
    $path = '/admin/reports/drupal-developer-assistant/modules/drupal_developer_assistant/php';
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(403);

    $this->loginInspectorUser();
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('PHP structure');
    $this->assertSession()->pageTextContains(
      'Only the 10 files on the current page are parsed',
    );
    $this->assertSession()->fieldExists('filter');
    $php_structure_table = '//table[caption[normalize-space(.)="PHP structure"]]';
    $this->assertSession()->elementsCount(
      'xpath',
      $php_structure_table . '/tbody/tr',
      10,
    );
    $this->assertSession()->elementExists('css', 'nav.pager');

    $this->drupalGet($path, [
      'query' => ['filter' => 'src/Architecture/ModuleArchitectureBuilder.php'],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals(
      'filter',
      'src/Architecture/ModuleArchitectureBuilder.php',
    );
    $this->assertSession()->pageTextContains('1 PHP file found.');
    $this->assertSession()->elementTextContains(
      'xpath',
      $php_structure_table,
      'Drupal\drupal_developer_assistant\Architecture',
    );
    $this->assertSession()->elementTextContains(
      'xpath',
      $php_structure_table,
      'final class Drupal\drupal_developer_assistant\Architecture\ModuleArchitectureBuilder',
    );
    $this->assertSession()->elementTextContains(
      'xpath',
      $php_structure_table,
      '__construct(',
    );
    $this->assertSession()->linkByHrefExists($path);
    $this->assertSession()->elementNotExists('css', 'nav.pager');

    $this->drupalGet(
      '/admin/reports/drupal-developer-assistant/modules/not_enabled/php',
    );
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests filtering and pagination on module relationships.
   */
  public function testModuleRelationshipsPage(): void {
    $path = '/admin/reports/drupal-developer-assistant/modules/drupal_developer_assistant/relationships';
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(403);

    $this->loginInspectorUser();
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Module relationships');
    $this->assertSession()->pageTextContains(
      'displays 25 rows at a time',
    );
    $this->assertSession()->fieldExists('filter');
    $relationship_table = '//table[caption[normalize-space(.)="Module relationships"]]';
    $this->assertSession()->elementsCount(
      'xpath',
      $relationship_table . '/tbody/tr',
      25,
    );
    $this->assertSession()->elementExists('css', 'nav.pager');
    $this->assertSession()->elementNotExists(
      'xpath',
      '//table[caption[normalize-space(.)="Owned components"]]',
    );
    $this->assertSession()->elementNotExists(
      'xpath',
      '//table[caption[normalize-space(.)="Source files"]]',
    );
    $this->assertSession()->elementNotExists(
      'xpath',
      '//table[caption[normalize-space(.)="PHP structure"]]',
    );

    $this->drupalGet($path, [
      'query' => ['filter' => 'drupal_developer_assistant.overview'],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals(
      'filter',
      'drupal_developer_assistant.overview',
    );
    $this->assertSession()->elementTextContains(
      'xpath',
      $relationship_table,
      'route: drupal_developer_assistant.overview',
    );
    $this->assertSession()->elementTextContains(
      'xpath',
      $relationship_table,
      'controller: Drupal\drupal_developer_assistant\Controller\InspectorController',
    );
    $this->assertSession()->linkByHrefExists($path);
    $this->assertSession()->elementNotExists('css', 'nav.pager');
    $this->assertSession()->linkExists('Back to module overview');
    $this->assertSession()->linkExists('Preview AI context');

    $this->drupalGet(
      '/admin/reports/drupal-developer-assistant/modules/not_enabled/relationships',
    );
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests the local AI-context preview and its access control.
   */
  public function testModuleContextPreviewPage(): void {
    $path = '/admin/reports/drupal-developer-assistant/modules/drupal_developer_assistant/context';
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(403);

    $this->loginInspectorUser();
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('AI context preview');
    $this->assertSession()->pageTextContains(
      'returned by the agent’s Inspect Drupal Module tool',
    );
    $this->assertSession()->linkExists('Ask the AI Assistant');
    $this->assertSession()->linkByHrefExists(
      '/admin/config/ai/agents/explore?agent_id=drupal_developer_assistant&drupal_developer_assistant_new=1',
    );

    $document_table = '//table[caption[normalize-space(.)="Context document"]]';
    $selection_table = '//table[caption[normalize-space(.)="Context selection"]]';
    $limits_table = '//table[caption[normalize-space(.)="Applied limits"]]';
    $this->assertSession()->elementTextContains(
      'xpath',
      $document_table,
      'Drupal Developer Assistant',
    );
    $this->assertSession()->elementTextContains(
      'xpath',
      $document_table,
      'Schema version 1.3',
    );
    $this->assertSession()->elementTextContains(
      'xpath',
      $selection_table,
      'Php files',
    );
    $this->assertSession()->elementTextContains(
      'xpath',
      $selection_table,
      'Available',
    );
    $this->assertSession()->elementTextContains(
      'xpath',
      $selection_table,
      'Included',
    );
    $this->assertSession()->elementTextContains(
      'xpath',
      $limits_table,
      'String bytes 500',
    );
    $this->assertSession()->pageTextContains('"schema_version": "1.3"');
    $this->assertSession()->pageTextContains('"php_structure"');
    $this->assertSession()->pageTextContains('ModuleArchitectureBuilder');
    $this->assertSession()->pageTextContains('"relationship": "navigates_to"');
    $this->assertSession()->pageTextContains(
      'drupal_developer_assistant.links.task.yml',
    );
    $this->assertSession()->linkExists('Back to module overview');
    $this->drupalGet(
      '/admin/reports/drupal-developer-assistant/modules/not_enabled/context',
    );
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Logs in a user who can access the inspector.
   */
  private function loginInspectorUser(): void {
    $account = $this->drupalCreateUser([
      'access drupal developer assistant',
    ]);
    $this->assertNotFalse($account);
    $this->drupalLogin($account);
  }

}
