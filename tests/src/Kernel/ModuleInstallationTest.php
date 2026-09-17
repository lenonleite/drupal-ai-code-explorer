<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests installation of the Drupal Developer Assistant module.
 *
 * @group drupal_developer_assistant
 */
#[RunTestsInSeparateProcesses]
final class ModuleInstallationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'user',
    'file',
    'key',
    'ai',
    'options',
    'modeler_api',
    'ai_agents',
    'ai_agents_explorer',
    'drupal_developer_assistant',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['user']);
  }

  /**
   * Tests that Drupal registers the module after installation.
   */
  public function testModuleIsInstalled(): void {
    $module_handler = $this->container->get('module_handler');

    $this->assertTrue($module_handler->moduleExists('ai'));
    $this->assertTrue($module_handler->moduleExists('key'));
    $this->assertTrue($module_handler->moduleExists('ai_agents'));
    $this->assertTrue($module_handler->moduleExists('ai_agents_explorer'));
    $this->assertTrue(
      $module_handler->moduleExists('drupal_developer_assistant'),
    );
  }

}
