<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Plugin\AiFunctionCall;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\PluginManager\AiDataTypeConverterPluginManager;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai\Utility\ContextDefinitionNormalizer;
use Drupal\drupal_developer_assistant\Component\ModuleComponentDetailsResolverInterface;
use Drupal\drupal_developer_assistant\Context\ComponentContextBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides focused architecture evidence for one Drupal module component.
 */
#[FunctionCall(
  id: 'drupal_developer_assistant:inspect_component',
  function_name: 'drupal_developer_assistant_inspect_component',
  name: 'Inspect Drupal Component',
  description: 'Returns bounded, read-only evidence for one component returned by Inspect Drupal Module. Use the exact module ID, component type, and component ID from that result. Source-file entries are not supported.',
  group: 'information_tools',
  module_dependencies: ['drupal_developer_assistant'],
  context_definitions: [
    'module_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Module machine name'),
      description: new TranslatableMarkup('The enabled Drupal module machine name, for example automated_cron.'),
      required: TRUE,
    ),
    'component_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Component type'),
      description: new TranslatableMarkup('The exact component type returned by Inspect Drupal Module, for example service, route, controller, or hook_implementation.'),
      required: TRUE,
    ),
    'component_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Component ID'),
      description: new TranslatableMarkup('The exact component ID returned by Inspect Drupal Module.'),
      required: TRUE,
    ),
  ],
)]
final class InspectComponent extends FunctionCallBase implements ExecutableFunctionCallInterface {

  /**
   * Constructs an Inspect Component function call plugin.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ContextDefinitionNormalizer $context_definition_normalizer,
    AiDataTypeConverterPluginManager $data_type_converter_manager,
    private readonly ModuleComponentDetailsResolverInterface $componentResolver,
    private readonly ComponentContextBuilderInterface $contextBuilder,
    private readonly AccountProxyInterface $currentUser,
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $context_definition_normalizer,
      $data_type_converter_manager,
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface|static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.context_definition_normalizer'),
      $container->get('plugin.manager.ai_data_type_converter'),
      $container->get(ModuleComponentDetailsResolverInterface::class),
      $container->get(ComponentContextBuilderInterface::class),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL) {
    if (!$this->currentUser->hasPermission('access drupal developer assistant')) {
      throw new \RuntimeException('You do not have permission to inspect Drupal components.');
    }

    $module_id = trim((string) $this->getContextValue('module_id'));
    $component_type = trim((string) $this->getContextValue('component_type'));
    $component_id = trim((string) $this->getContextValue('component_id'));
    if (
      !preg_match('/^[a-z0-9_]+$/', $module_id)
      || !preg_match('/^[a-z0-9_]+$/', $component_type)
      || $component_id === ''
      || strlen($component_id) > 512
    ) {
      $this->storeOutput([
        'tool' => 'inspect_component',
        'status' => 'error',
        'message' => 'The module or component identifier is invalid.',
      ]);
      return;
    }

    $component_reference = $this->componentResolver->referenceId(
      $module_id,
      $component_type,
      $component_id,
    );
    $details = $this->componentResolver->resolve(
      $module_id,
      $component_reference,
    );
    if ($details === NULL) {
      $this->storeOutput([
        'tool' => 'inspect_component',
        'status' => 'error',
        'module_id' => $module_id,
        'component_type' => $component_type,
        'component_id' => $component_id,
        'message' => 'The requested component was not found in the enabled module.',
      ]);
      return;
    }

    $this->storeOutput([
      'tool' => 'inspect_component',
      'status' => 'success',
      'context' => $this->contextBuilder->build($details)->jsonSerialize(),
    ]);
  }

  /**
   * Stores both the model-readable and structured forms of a tool result.
   *
   * @param array<string, mixed> $output
   *   The tool result.
   */
  private function storeOutput(array $output): void {
    $this->setStructuredOutput($output);
    $this->setOutput((string) json_encode(
      $output,
      JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    ));
  }

}
