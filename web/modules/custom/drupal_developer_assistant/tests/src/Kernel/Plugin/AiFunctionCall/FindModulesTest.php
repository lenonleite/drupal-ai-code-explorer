<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Kernel\Plugin\AiFunctionCall;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Find Drupal Modules AI function call plugin.
 */
#[Group('drupal_developer_assistant')]
#[RunTestsInSeparateProcesses]
final class FindModulesTest extends KernelTestBase {

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
    'automated_cron',
    'drupal_developer_assistant',
  ];

  /**
   * Tests discovery and ranked matching against enabled module metadata.
   */
  public function testFindsEnabledModules(): void {
    $this->setCurrentUserPermission(TRUE);

    $manager = $this->container->get('plugin.manager.ai.function_calls');
    $manager->clearCachedDefinitions();
    $this->assertTrue(
      $manager->hasDefinition('drupal_developer_assistant:find_modules'),
    );

    $tool = $manager->createInstance(
      'drupal_developer_assistant:find_modules',
    );
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    $tool->setContextValue('query', 'what module works with cron');
    $tool->execute();

    $output = json_decode(
      $tool->getReadableOutput(),
      TRUE,
      flags: JSON_THROW_ON_ERROR,
    );
    $this->assertSame('find_modules', $output['tool']);
    $this->assertSame('success', $output['status']);
    $this->assertSame('automated_cron', $output['matches'][0]['id']);
    $this->assertContains('id', $output['matches'][0]['matched_fields']);
    $this->assertGreaterThanOrEqual(1, $output['summary']['matches_found']);
    $this->assertLessThanOrEqual(10, $output['summary']['matches_returned']);
    $this->assertSame($output, $tool->getStructuredOutput());
  }

  /**
   * Tests invalid, nonspecific, and unmatched queries.
   */
  public function testQueryValidationAndNoMatches(): void {
    $this->setCurrentUserPermission(TRUE);
    $manager = $this->container->get('plugin.manager.ai.function_calls');

    $empty_tool = $manager->createInstance(
      'drupal_developer_assistant:find_modules',
    );
    $empty_tool->setContextValue('query', '');
    $empty_tool->execute();
    $empty_output = json_decode(
      $empty_tool->getReadableOutput(),
      TRUE,
      flags: JSON_THROW_ON_ERROR,
    );
    $this->assertSame('error', $empty_output['status']);

    $generic_tool = $manager->createInstance(
      'drupal_developer_assistant:find_modules',
    );
    $generic_tool->setContextValue('query', 'what is the Drupal module');
    $generic_tool->execute();
    $generic_output = json_decode(
      $generic_tool->getReadableOutput(),
      TRUE,
      flags: JSON_THROW_ON_ERROR,
    );
    $this->assertSame('error', $generic_output['status']);

    $missing_tool = $manager->createInstance(
      'drupal_developer_assistant:find_modules',
    );
    $missing_tool->setContextValue('query', 'zzzznonexistentzzzz');
    $missing_tool->execute();
    $missing_output = json_decode(
      $missing_tool->getReadableOutput(),
      TRUE,
      flags: JSON_THROW_ON_ERROR,
    );
    $this->assertSame('success', $missing_output['status']);
    $this->assertSame([], $missing_output['matches']);
  }

  /**
   * Tests that the tool enforces the assistant access permission.
   */
  public function testAccessDenied(): void {
    $this->setCurrentUserPermission(FALSE);
    $manager = $this->container->get('plugin.manager.ai.function_calls');
    $tool = $manager->createInstance(
      'drupal_developer_assistant:find_modules',
    );
    $tool->setContextValue('query', 'cron');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(
      'You do not have permission to find Drupal modules.',
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
