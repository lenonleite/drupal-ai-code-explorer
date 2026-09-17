<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Context;

use Drupal\drupal_developer_assistant\Model\ComponentRelationship;
use Drupal\drupal_developer_assistant\Model\ModuleArchitecture;
use Drupal\drupal_developer_assistant\Model\ModuleComponentDetails;

/**
 * Builds a bounded context by reusing the module context normalizer.
 */
final class ComponentContextBuilder implements ComponentContextBuilderInterface {

  /**
   * Constructs a component context builder.
   */
  public function __construct(
    private readonly ModuleContextBuilderInterface $moduleContextBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function build(ModuleComponentDetails $details): ComponentContext {
    $relationships = $this->uniqueRelationships([
      ...$details->outgoingRelationships,
      ...$details->incomingRelationships,
    ]);
    $architecture = new ModuleArchitecture(
      module: $details->module,
      components: [$details->component],
      relationships: $relationships,
      sourceFiles: [],
      phpFiles: $details->phpFiles,
    );
    $normalized = $this->moduleContextBuilder
      ->build($architecture)
      ->jsonSerialize();
    $component = $normalized['components'][0] ?? NULL;
    if (!is_array($component)) {
      throw new \UnexpectedValueException('Component context requires one normalized component.');
    }
    $component['evidence_reference'] = 'context.component';

    $outgoing = [];
    $incoming = [];
    foreach ($normalized['relationships'] as $relationship) {
      if ($this->matches($relationship['source'], $component)) {
        $relationship['evidence_reference'] = sprintf(
          'context.outgoing_relationships[%d]',
          count($outgoing),
        );
        $outgoing[] = $relationship;
      }
      if ($this->matches($relationship['target'], $component)) {
        $relationship['evidence_reference'] = sprintf(
          'context.incoming_relationships[%d]',
          count($incoming),
        );
        $incoming[] = $relationship;
      }
    }

    $php_structure = $normalized['php_structure'];
    foreach ($php_structure as $index => &$php_file) {
      $php_file['evidence_reference'] = sprintf(
        'context.php_structure[%d]',
        $index,
      );
    }
    unset($php_file);

    return new ComponentContext(
      module: $normalized['module'],
      component: $component,
      summary: $normalized['summary'],
      outgoingRelationships: $outgoing,
      incomingRelationships: $incoming,
      phpStructure: $php_structure,
    );
  }

  /**
   * Removes a duplicated self-referencing relationship by object identity.
   *
   * @param list<\Drupal\drupal_developer_assistant\Model\ComponentRelationship> $relationships
   *   Incoming and outgoing relationships.
   *
   * @return list<\Drupal\drupal_developer_assistant\Model\ComponentRelationship>
   *   Relationships in their original deterministic order.
   */
  private function uniqueRelationships(array $relationships): array {
    $unique = [];
    $seen = [];
    foreach ($relationships as $relationship) {
      if (!$relationship instanceof ComponentRelationship) {
        throw new \UnexpectedValueException('Component relationships must be ComponentRelationship objects.');
      }
      $object_id = spl_object_id($relationship);
      if (!isset($seen[$object_id])) {
        $unique[] = $relationship;
        $seen[$object_id] = TRUE;
      }
    }

    return $unique;
  }

  /**
   * Determines whether one normalized endpoint identifies the component.
   *
   * @param array{type: string, id: string} $endpoint
   *   A normalized relationship source or target.
   * @param array<string, mixed> $component
   *   The selected normalized component.
   */
  private function matches(array $endpoint, array $component): bool {
    return $endpoint['type'] === $component['type']
      && $endpoint['id'] === $component['id'];
  }

}
