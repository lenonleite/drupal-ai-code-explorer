<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Relationship;

use Drupal\drupal_developer_assistant\Model\ComponentRelationship;

/**
 * Stores registered relationship resolvers.
 */
final class RelationshipManager implements RelationshipManagerInterface {

  /**
   * The resolvers, keyed by identifier.
   *
   * @var array<string, \Drupal\drupal_developer_assistant\Relationship\RelationshipResolverInterface>
   */
  private array $resolvers = [];

  /**
   * {@inheritdoc}
   */
  public function addResolver(RelationshipResolverInterface $resolver): void {
    $id = $resolver->id();
    if (isset($this->resolvers[$id])) {
      throw new \LogicException(sprintf('A relationship resolver with ID "%s" is already registered.', $id));
    }

    $this->resolvers[$id] = $resolver;
  }

  /**
   * {@inheritdoc}
   */
  public function get(string $id): RelationshipResolverInterface {
    if (!isset($this->resolvers[$id])) {
      throw new \InvalidArgumentException(sprintf('Relationship resolver "%s" is not registered.', $id));
    }

    return $this->resolvers[$id];
  }

  /**
   * {@inheritdoc}
   */
  public function all(): array {
    return $this->resolvers;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveAll(): array {
    $relationships = [];
    foreach ($this->resolvers as $resolver) {
      foreach ($resolver->resolve() as $relationship) {
        if (!$relationship instanceof ComponentRelationship) {
          throw new \UnexpectedValueException('Relationship resolvers must return ComponentRelationship objects.');
        }
        $relationships[] = $relationship;
      }
    }

    return $relationships;
  }

}
