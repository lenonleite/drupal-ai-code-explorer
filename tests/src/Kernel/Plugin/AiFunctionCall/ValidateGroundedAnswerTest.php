<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Kernel\Plugin\AiFunctionCall;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\drupal_developer_assistant\Agent\GroundedAnswerRepairerInterface;
use Drupal\drupal_developer_assistant\Architecture\ModuleArchitectureBuilderInterface;
use Drupal\drupal_developer_assistant\Retrieval\SourceSnippet;
use Drupal\drupal_developer_assistant\Retrieval\SourceSnippetRetrieverInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the required final-answer validation function call.
 */
#[Group('drupal_developer_assistant')]
#[RunTestsInSeparateProcesses]
final class ValidateGroundedAnswerTest extends KernelTestBase {

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
   * Tests direct return of an answer that passes on its first validation.
   */
  public function testReturnsInitiallyValidAnswer(): void {
    $this->setCurrentUserPermissions();
    [$question, $answer] = $this->validAnswerFixture();
    $repairer = $this->createMock(GroundedAnswerRepairerInterface::class);
    $repairer->expects($this->never())->method('repair');
    $this->container->set(GroundedAnswerRepairerInterface::class, $repairer);

    $tool = $this->createTool($question, $answer);
    $tool->execute();

    $this->assertSame($answer, $tool->getReadableOutput());
    $this->assertSame('success', $tool->getStructuredOutput()['status']);
    $this->assertFalse($tool->getStructuredOutput()['repaired']);
  }

  /**
   * Tests the single repair path for an invalid initial answer.
   */
  public function testRepairsInvalidAnswerOnce(): void {
    $this->setCurrentUserPermissions();
    [$question, $valid_answer] = $this->validAnswerFixture();
    $repairer = $this->createMock(GroundedAnswerRepairerInterface::class);
    $repairer
      ->expects($this->once())
      ->method('repair')
      ->willReturn($valid_answer);
    $this->container->set(GroundedAnswerRepairerInterface::class, $repairer);

    $tool = $this->createTool($question, 'An unsupported answer.');
    $tool->execute();

    $this->assertSame($valid_answer, $tool->getReadableOutput());
    $this->assertSame('success', $tool->getStructuredOutput()['status']);
    $this->assertTrue($tool->getStructuredOutput()['repaired']);
  }

  /**
   * Creates the validation tool with its required context.
   */
  private function createTool(string $question, string $answer): object {
    $manager = $this->container->get('plugin.manager.ai.function_calls');
    $manager->clearCachedDefinitions();
    $this->assertTrue($manager->hasDefinition(
      'drupal_developer_assistant:validate_grounded_answer',
    ));
    $tool = $manager->createInstance(
      'drupal_developer_assistant:validate_grounded_answer',
    );
    $tool->setContextValue('module_id', 'drupal_developer_assistant');
    $tool->setContextValue('question', $question);
    $tool->setContextValue('answer', $answer);
    return $tool;
  }

  /**
   * Builds an answer from the same deterministic evidence used by the tool.
   *
   * @return array{string, string}
   *   Question and a valid grounded answer.
   */
  private function validAnswerFixture(): array {
    $question = 'How does InspectComponent execute a component inspection?';
    $architecture = $this->container
      ->get(ModuleArchitectureBuilderInterface::class)
      ->build('drupal_developer_assistant');
    $this->assertNotNull($architecture);
    $snippets = $this->container
      ->get(SourceSnippetRetrieverInterface::class)
      ->retrieve($architecture, $question);
    $this->assertNotEmpty($snippets);
    $snippet = $snippets[0];
    $this->assertInstanceOf(SourceSnippet::class, $snippet);
    $path = rtrim((string) $architecture->module->sourcePath, '/')
      . '/' . ltrim($snippet->path, '/');
    $answer = implode("\n", [
      '### Confirmed by source',
      sprintf(
        '- The retrieved declaration handles the inspected behavior. Evidence: `%s:%d-%d — %s`',
        $path,
        $snippet->startLine,
        $snippet->endLine,
        $snippet->symbol,
      ),
    ]);
    $redactions = array_sum(array_map(
      static fn(SourceSnippet $item): int => $item->redactions,
      $snippets,
    ));
    if ($redactions > 0) {
      $answer .= "\n\n### Unavailable because redacted\n"
        . '- Exact literal values removed from the source cannot be confirmed.';
    }
    return [$question, $answer];
  }

  /**
   * Replaces the current user with a permission-aware test double.
   */
  private function setCurrentUserPermissions(): void {
    $current_user = $this->createMock(AccountProxyInterface::class);
    $current_user
      ->method('hasPermission')
      ->willReturnCallback(static fn(string $permission): bool => in_array(
        $permission,
        [
          'access drupal developer assistant',
          'view drupal developer assistant source',
        ],
        TRUE,
      ));
    $this->container->set('current_user', $current_user);
  }

}
