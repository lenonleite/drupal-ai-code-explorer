<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Kernel\Plugin\AiFunctionCall;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Search Module Source AI function call plugin.
 */
#[Group('drupal_developer_assistant')]
#[RunTestsInSeparateProcesses]
final class SearchModuleSourceTest extends KernelTestBase {

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
   * Tests plugin discovery and relevant bounded source output.
   */
  public function testSearchModuleSource(): void {
    $this->setCurrentUserPermissions(TRUE, TRUE);

    $manager = $this->container->get('plugin.manager.ai.function_calls');
    $manager->clearCachedDefinitions();
    $this->assertTrue($manager->hasDefinition('drupal_developer_assistant:search_module_source'));

    $tool = $manager->createInstance('drupal_developer_assistant:search_module_source');
    $this->assertInstanceOf(ExecutableFunctionCallInterface::class, $tool);
    $tool->setContextValue('module_id', 'drupal_developer_assistant');
    $tool->setContextValue(
      'question',
      'How does InspectComponent execute a component inspection?',
    );
    $tool->execute();

    $output = json_decode(
      $tool->getReadableOutput(),
      TRUE,
      flags: JSON_THROW_ON_ERROR,
    );
    $this->assertSame('search_module_source', $output['tool']);
    $this->assertSame('success', $output['status']);
    $this->assertSame(
      'drupal_developer_assistant',
      $output['module']['id'],
    );
    $this->assertSame(
      'bounded_lexical_retrieval',
      $output['summary']['strategy'],
    );
    $this->assertGreaterThan(0, $output['summary']['snippet_count']);
    $this->assertFalse($output['summary']['fallback_used']);
    $this->assertNotEmpty($output['snippets']);
    $this->assertStringContainsString(
      'InspectComponent',
      $output['snippets'][0]['symbol'],
    );
    $this->assertGreaterThan(0, $output['snippets'][0]['score']);
    $this->assertSame($output, $tool->getStructuredOutput());
  }

  /**
   * Tests invalid questions and unavailable modules.
   */
  public function testSearchValidation(): void {
    $this->setCurrentUserPermissions(TRUE, TRUE);
    $manager = $this->container->get('plugin.manager.ai.function_calls');

    $invalid_tool = $manager->createInstance('drupal_developer_assistant:search_module_source');
    $invalid_tool->setContextValue('module_id', 'drupal_developer_assistant');
    $invalid_tool->setContextValue('question', '   ');
    $invalid_tool->execute();
    $invalid_output = json_decode(
      $invalid_tool->getReadableOutput(),
      TRUE,
      flags: JSON_THROW_ON_ERROR,
    );
    $this->assertSame('error', $invalid_output['status']);
    $this->assertSame(
      'The module machine name or developer question is invalid.',
      $invalid_output['message'],
    );

    $missing_tool = $manager->createInstance('drupal_developer_assistant:search_module_source');
    $missing_tool->setContextValue('module_id', 'not_an_enabled_module');
    $missing_tool->setContextValue('question', 'How does it work?');
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
   * Tests that source evidence requires its dedicated permission.
   */
  public function testSourceAccessDenied(): void {
    $this->setCurrentUserPermissions(TRUE, FALSE);
    $manager = $this->container->get('plugin.manager.ai.function_calls');
    $tool = $manager->createInstance('drupal_developer_assistant:search_module_source');
    $tool->setContextValue('module_id', 'drupal_developer_assistant');
    $tool->setContextValue('question', 'How does it work?');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(
      'You do not have permission to view Drupal source evidence.',
    );
    $tool->execute();
  }

  /**
   * Replaces the current-user service with a permission-aware test double.
   */
  private function setCurrentUserPermissions(
    bool $access_assistant,
    bool $view_source,
  ): void {
    $permissions = [
      'access drupal developer assistant' => $access_assistant,
      'view drupal developer assistant source' => $view_source,
    ];
    $current_user = $this->createMock(AccountProxyInterface::class);
    $current_user
      ->expects($this->atLeastOnce())
      ->method('hasPermission')
      ->willReturnCallback(
        static fn(string $permission): bool => $permissions[$permission] ?? FALSE,
      );
    $this->container->set('current_user', $current_user);
  }

}
