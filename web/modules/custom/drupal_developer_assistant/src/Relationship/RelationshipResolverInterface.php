<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Relationship;

/**
 * Defines a resolver that discovers relationships between components.
 */
interface RelationshipResolverInterface {

  /**
   * Returns the resolver's unique identifier.
   */
  public function id(): string;

  /**
   * Resolves component relationships.
   *
   * @return list<\Drupal\drupal_developer_assistant\Model\ComponentRelationship>
   *   The discovered relationships.
   */
  public function resolve(): array;

}
