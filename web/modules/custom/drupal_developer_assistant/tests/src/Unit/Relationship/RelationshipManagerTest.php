<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Relationship;

use Drupal\drupal_developer_assistant\Model\ComponentRelationship;
use Drupal\drupal_developer_assistant\Relationship\RelationshipManager;
use Drupal\drupal_developer_assistant\Relationship\RelationshipResolverInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the relationship resolver registry.
 */
#[Group('drupal_developer_assistant')]
final class RelationshipManagerTest extends UnitTestCase {

  /**
   * Tests registration, lookup, and aggregation.
   */
  public function testAddGetAllAndResolveAll(): void {
    $first_relationship = new ComponentRelationship(
      'route',
      'example.first',
      'invokes',
      'controller',
      'Drupal\\example\\FirstController',
    );
    $second_relationship = new ComponentRelationship(
      'route',
      'example.second',
      'requires',
      'permission',
      'access example',
    );
    $first = $this->createResolver('first', [$first_relationship]);
    $second = $this->createResolver('second', [$second_relationship]);
    $manager = new RelationshipManager();

    $manager->addResolver($first);
    $manager->addResolver($second);

    $this->assertSame($first, $manager->get('first'));
    $this->assertSame([
      'first' => $first,
      'second' => $second,
    ], $manager->all());
    $this->assertSame(
      [$first_relationship, $second_relationship],
      $manager->resolveAll(),
    );
  }

  /**
   * Tests that resolver identifiers must be unique.
   */
  public function testDuplicateIdIsRejected(): void {
    $manager = new RelationshipManager();
    $manager->addResolver($this->createResolver('routes'));

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('A relationship resolver with ID "routes" is already registered.');
    $manager->addResolver($this->createResolver('routes'));
  }

  /**
   * Tests that an unknown resolver cannot be retrieved.
   */
  public function testUnknownIdIsRejected(): void {
    $manager = new RelationshipManager();

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Relationship resolver "routes" is not registered.');
    $manager->get('routes');
  }

  /**
   * Creates a relationship resolver test double.
   *
   * @param string $id
   *   The resolver identifier.
   * @param array $relationships
   *   Relationships returned by the resolver.
   */
  private function createResolver(
    string $id,
    array $relationships = [],
  ): RelationshipResolverInterface {
    return new class($id, $relationships) implements RelationshipResolverInterface {

      /**
       * Constructs a resolver test double.
       *
       * @param string $resolverId
       *   The resolver identifier.
       * @param array $relationships
       *   Relationships returned by the resolver.
       */
      public function __construct(
        private readonly string $resolverId,
        private readonly array $relationships,
      ) {}

      /**
       * {@inheritdoc}
       */
      public function id(): string {
        return $this->resolverId;
      }

      /**
       * {@inheritdoc}
       */
      public function resolve(): array {
        return $this->relationships;
      }

    };
  }

}
