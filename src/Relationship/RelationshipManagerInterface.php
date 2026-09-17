<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Relationship;

/**
 * Defines the registry for component relationship resolvers.
 */
interface RelationshipManagerInterface {

  /**
   * Registers a relationship resolver.
   */
  public function addResolver(RelationshipResolverInterface $resolver): void;

  /**
   * Returns a relationship resolver by identifier.
   */
  public function get(string $id): RelationshipResolverInterface;

  /**
   * Returns every registered relationship resolver.
   *
   * @return array<string, \Drupal\drupal_developer_assistant\Relationship\RelationshipResolverInterface>
   *   Resolvers keyed by identifier.
   */
  public function all(): array;

  /**
   * Resolves relationships from every registered resolver.
   *
   * @return list<\Drupal\drupal_developer_assistant\Model\ComponentRelationship>
   *   All discovered relationships.
   */
  public function resolveAll(): array;

}
