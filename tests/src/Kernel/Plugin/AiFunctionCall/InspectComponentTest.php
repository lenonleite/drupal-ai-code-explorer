<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Kernel\Plugin\AiFunctionCall;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Inspect Component AI function call plugin.
 */
#[Group('drupal_developer_assistant')]
#[RunTestsInSeparateProcesses]
final class InspectComponentTest extends KernelTestBase {

  /**
   * A stable component discovered from this module's controller services.
   */
  private const COMPONENT_ID = 'Drupal\drupal_developer_assistant\Controller\InspectorController';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'key',
    'ai',
    'drupal_developer_assistant',
  ];

  /**
   * Tests plugin discovery and focused component output.
   */
  public function testInspectComponent(): void {
    $this->setCurrentUserPermission(TRUE);

    $manager = $this->container->get('plugin.manager.ai.function_calls');
    $manager->clearCachedDefinitions();
    $this->assertTrue($manager->hasDefinition('drupal_developer_assistant:inspect_component'));

    $tool = $manager->createInstance('drupal_developer_assistant:inspect_component');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    $tool->setContextValue('module_id', 'drupal_developer_assistant');
    $tool->setContextValue('component_type', 'controller');
    $tool->setContextValue('component_id', self::COMPONENT_ID);
    $tool->execute();

    $output = json_decode(
      $tool->getReadableOutput(),
      TRUE,
      flags: JSON_THROW_ON_ERROR,
    );
    $this->assertSame('inspect_component', $output['tool']);
    $this->assertSame('success', $output['status']);
    $this->assertSame('1.1', $output['context']['schema_version']);
    $this->assertSame(
      'drupal_developer_assistant',
      $output['context']['module']['id'],
    );
    $this->assertSame('controller', $output['context']['component']['type']);
    $this->assertSame(
      self::COMPONENT_ID,
      $output['context']['component']['id'],
    );
    $this->assertNotEmpty($output['context']['php_structure']);
    $this->assertSame($output, $tool->getStructuredOutput());
  }

  /**
   * Tests invalid and unknown component identifiers.
   */
  public function testComponentValidation(): void {
    $this->setCurrentUserPermission(TRUE);
    $manager = $this->container->get('plugin.manager.ai.function_calls');

    $invalid_tool = $manager->createInstance('drupal_developer_assistant:inspect_component');
    $invalid_tool->setContextValue('module_id', 'drupal_developer_assistant');
    $invalid_tool->setContextValue('component_type', '../controller');
    $invalid_tool->setContextValue('component_id', self::COMPONENT_ID);
    $invalid_tool->execute();
    $invalid_output = json_decode(
      $invalid_tool->getReadableOutput(),
      TRUE,
      flags: JSON_THROW_ON_ERROR,
    );
    $this->assertSame('error', $invalid_output['status']);
    $this->assertSame(
      'The module or component identifier is invalid.',
      $invalid_output['message'],
    );

    $missing_tool = $manager->createInstance('drupal_developer_assistant:inspect_component');
    $missing_tool->setContextValue('module_id', 'drupal_developer_assistant');
    $missing_tool->setContextValue('component_type', 'controller');
    $missing_tool->setContextValue('component_id', 'Drupal\Example\MissingController');
    $missing_tool->execute();
    $missing_output = json_decode(
      $missing_tool->getReadableOutput(),
      TRUE,
      flags: JSON_THROW_ON_ERROR,
    );
    $this->assertSame('error', $missing_output['status']);
    $this->assertSame(
      'The requested component was not found in the enabled module.',
      $missing_output['message'],
    );
  }

  /**
   * Tests that the tool enforces the assistant access permission.
   */
  public function testAccessDenied(): void {
    $this->setCurrentUserPermission(FALSE);
    $manager = $this->container->get('plugin.manager.ai.function_calls');
    $tool = $manager->createInstance('drupal_developer_assistant:inspect_component');
    $tool->setContextValue('module_id', 'drupal_developer_assistant');
    $tool->setContextValue('component_type', 'controller');
    $tool->setContextValue('component_id', self::COMPONENT_ID);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(
      'You do not have permission to inspect Drupal components.',
    );
    $tool->execute();
  }

  /**
   * Replaces the current-user service with a permission-aware test double.
   */
  private function setCurrentUserPermission(bool $allowed): void {
    $current_user = $this->createMock(AccountProxyInterface::class);
    $current_user
      ->expects($this->atLeastOnce())
      ->method('hasPermission')
      ->with('access drupal developer assistant')
      ->willReturn($allowed);
    $this->container->set('current_user', $current_user);
  }

}
