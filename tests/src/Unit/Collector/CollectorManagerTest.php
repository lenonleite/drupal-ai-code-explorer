<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Collector;

use Drupal\drupal_developer_assistant\Collector\CollectorInterface;
use Drupal\drupal_developer_assistant\Collector\CollectorManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the collector manager.
 */
#[Group('drupal_developer_assistant')]
final class CollectorManagerTest extends UnitTestCase {

  /**
   * Tests registration and lookup by collector identifier.
   */
  public function testAddGetAndAll(): void {
    $collector = $this->createCollector('modules');
    $manager = new CollectorManager();

    $manager->addCollector($collector);

    $this->assertSame($collector, $manager->get('modules'));
    $this->assertSame(['modules' => $collector], $manager->all());
  }

  /**
   * Tests that collector identifiers must be unique.
   */
  public function testDuplicateIdIsRejected(): void {
    $manager = new CollectorManager();
    $manager->addCollector($this->createCollector('modules'));

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('A collector with ID "modules" is already registered.');
    $manager->addCollector($this->createCollector('modules'));
  }

  /**
   * Tests that an unknown collector cannot be retrieved.
   */
  public function testUnknownIdIsRejected(): void {
    $manager = new CollectorManager();

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Collector "routes" is not registered.');
    $manager->get('routes');
  }

  /**
   * Creates a collector test double with the given identifier.
   */
  private function createCollector(string $id): CollectorInterface {
    return new class($id) implements CollectorInterface {

      /**
       * Constructs a collector test double.
       */
      public function __construct(
        private readonly string $collectorId,
      ) {}

      /**
       * {@inheritdoc}
       */
      public function id(): string {
        return $this->collectorId;
      }

      /**
       * {@inheritdoc}
       */
      public function collect(): array {
        return [];
      }

    };
  }

}
