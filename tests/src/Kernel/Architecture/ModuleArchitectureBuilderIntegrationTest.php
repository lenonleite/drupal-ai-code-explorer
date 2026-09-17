<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Kernel\Architecture;

use Drupal\drupal_developer_assistant\Analyzer\PhpSourceAnalyzer;
use Drupal\drupal_developer_assistant\Analyzer\PhpSourceAnalyzerInterface;
use Drupal\drupal_developer_assistant\Architecture\ModuleArchitectureCache;
use Drupal\drupal_developer_assistant\Architecture\ModuleArchitectureCacheInterface;
use Drupal\drupal_developer_assistant\Architecture\ModuleArchitectureBuilder;
use Drupal\drupal_developer_assistant\Architecture\ModuleArchitectureBuilderInterface;
use Drupal\drupal_developer_assistant\Collector\ModuleSourceFileCollector;
use Drupal\drupal_developer_assistant\Collector\ModuleSourceFileCollectorInterface;
use Drupal\drupal_developer_assistant\Component\ModuleComponentDetailsResolver;
use Drupal\drupal_developer_assistant\Component\ModuleComponentDetailsResolverInterface;
use Drupal\drupal_developer_assistant\Context\ComponentContextBuilder;
use Drupal\drupal_developer_assistant\Context\ComponentContextBuilderInterface;
use Drupal\drupal_developer_assistant\Context\ModuleContextBuilder;
use Drupal\drupal_developer_assistant\Context\ModuleContextBuilderInterface;
use Drupal\drupal_developer_assistant\Controller\ModuleContextController;
use Drupal\drupal_developer_assistant\Relationship\PhpDependencyResolver;
use Drupal\drupal_developer_assistant\Relationship\PhpDependencyResolverInterface;
use Drupal\drupal_developer_assistant\Retrieval\SourceRetrievalLimits;
use Drupal\drupal_developer_assistant\Retrieval\SourceSnippetRetriever;
use Drupal\drupal_developer_assistant\Retrieval\SourceSnippetRetrieverInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Drupal service integration for the module architecture builder.
 */
#[Group('drupal_developer_assistant')]
#[RunTestsInSeparateProcesses]
final class ModuleArchitectureBuilderIntegrationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
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
   * Tests construction through the builder's interface service alias.
   */
  public function testBuilderServiceAlias(): void {
    $builder = $this->container->get(
      ModuleArchitectureBuilderInterface::class,
    );

    $this->assertInstanceOf(ModuleArchitectureBuilder::class, $builder);
    $this->assertInstanceOf(
      ModuleArchitectureCache::class,
      $this->container->get(ModuleArchitectureCacheInterface::class),
    );
    $this->assertInstanceOf(
      ModuleSourceFileCollector::class,
      $this->container->get(ModuleSourceFileCollectorInterface::class),
    );
    $this->assertInstanceOf(
      PhpSourceAnalyzer::class,
      $this->container->get(PhpSourceAnalyzerInterface::class),
    );
    $this->assertInstanceOf(
      PhpDependencyResolver::class,
      $this->container->get(PhpDependencyResolverInterface::class),
    );
    $this->assertInstanceOf(
      ModuleContextBuilder::class,
      $this->container->get(ModuleContextBuilderInterface::class),
    );
    $this->assertInstanceOf(
      ComponentContextBuilder::class,
      $this->container->get(ComponentContextBuilderInterface::class),
    );
    $this->assertInstanceOf(
      ModuleComponentDetailsResolver::class,
      $this->container->get(ModuleComponentDetailsResolverInterface::class),
    );
    $this->assertInstanceOf(
      ModuleContextController::class,
      $this->container->get(ModuleContextController::class),
    );
    $this->assertInstanceOf(
      SourceRetrievalLimits::class,
      $this->container->get(SourceRetrievalLimits::class),
    );
    $this->assertInstanceOf(
      SourceSnippetRetriever::class,
      $this->container->get(SourceSnippetRetrieverInterface::class),
    );
    $architecture = $builder->build('drupal_developer_assistant');
    $this->assertNotNull($architecture);
    $component_keys = array_map(
      static fn($component): string => $component->type . ':' . $component->id,
      $architecture->components,
    );
    $this->assertContains(
      'menu_link:drupal_developer_assistant.overview',
      $component_keys,
    );
    $this->assertContains(
      'local_task:drupal_developer_assistant.modules',
      $component_keys,
    );

    $transition_keys = [];
    foreach ($architecture->relationships as $relationship) {
      if ($relationship->relationship === 'navigates_to') {
        $transition_keys[] = $relationship->sourceId
          . ' -> '
          . $relationship->targetId;
      }
    }
    $this->assertContains(
      'system.admin_reports -> drupal_developer_assistant.overview',
      $transition_keys,
    );
    $this->assertContains(
      'drupal_developer_assistant.overview -> drupal_developer_assistant.modules',
      $transition_keys,
    );

    $context = $this->container
      ->get(ModuleContextBuilderInterface::class)
      ->build($architecture)
      ->jsonSerialize();
    $this->assertSame('1.3', $context['schema_version']);
    $this->assertContains(
      'navigates_to',
      array_column($context['relationships'], 'relationship'),
    );
  }

}
