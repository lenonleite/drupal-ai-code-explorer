<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Kernel\Plugin\AiFunctionCall;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Inspect Module AI function call plugin.
 */
#[Group('drupal_developer_assistant')]
#[RunTestsInSeparateProcesses]
final class InspectModuleTest extends KernelTestBase {

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
   * Tests plugin discovery and successful bounded output.
   */
  public function testInspectEnabledModule(): void {
    $this->setCurrentUserPermission(TRUE);

    $manager = $this->container->get('plugin.manager.ai.function_calls');
    $manager->clearCachedDefinitions();
    $this->assertTrue($manager->hasDefinition('drupal_developer_assistant:inspect_module'));

    $tool = $manager->createInstance('drupal_developer_assistant:inspect_module');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    $tool->setContextValue('module_id', 'drupal_developer_assistant');
    $tool->execute();

    $output = json_decode(
      $tool->getReadableOutput(),
      TRUE,
      flags: JSON_THROW_ON_ERROR,
    );
    $this->assertSame('inspect_module', $output['tool']);
    $this->assertSame('success', $output['status']);
    $this->assertSame('1.3', $output['context']['schema_version']);
    $this->assertSame(
      'drupal_developer_assistant',
      $output['context']['module']['id'],
    );
    $this->assertSame($output, $tool->getStructuredOutput());
  }

  /**
   * Tests that the tool handles invalid and unavailable module identifiers.
   */
  public function testModuleValidation(): void {
    $this->setCurrentUserPermission(TRUE);
    $manager = $this->container->get('plugin.manager.ai.function_calls');

    $invalid_tool = $manager->createInstance('drupal_developer_assistant:inspect_module');
    $invalid_tool->setContextValue('module_id', '../settings');
    $invalid_tool->execute();
    $invalid_output = json_decode(
      $invalid_tool->getReadableOutput(),
      TRUE,
      flags: JSON_THROW_ON_ERROR,
    );
    $this->assertSame('error', $invalid_output['status']);
    $this->assertSame(
      'The module machine name is invalid.',
      $invalid_output['message'],
    );

    $missing_tool = $manager->createInstance('drupal_developer_assistant:inspect_module');
    $missing_tool->setContextValue('module_id', 'not_an_enabled_module');
    $missing_tool->execute();
    $missing_output = json_decode(
      $missing_tool->getReadableOutput(),
      TRUE,
      flags: JSON_THROW_ON_ERROR,
    );
    $this->assertSame('error', $missing_output['status']);
    $this->assertSame(
      'The requested module is not enabled.',
      $missing_output['message'],
    );
  }

  /**
   * Tests that the tool enforces the assistant access permission.
   */
  public function testAccessDenied(): void {
    $this->setCurrentUserPermission(FALSE);
    $manager = $this->container->get('plugin.manager.ai.function_calls');
    $tool = $manager->createInstance('drupal_developer_assistant:inspect_module');
    $tool->setContextValue('module_id', 'drupal_developer_assistant');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(
      'You do not have permission to inspect Drupal modules.',
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
