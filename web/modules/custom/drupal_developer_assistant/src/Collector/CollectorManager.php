<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Collector;

/**
 * Stores the registered component collectors.
 */
final class CollectorManager implements CollectorManagerInterface {

  /**
   * The collectors, keyed by identifier.
   *
   * @var array<string, \Drupal\drupal_developer_assistant\Collector\CollectorInterface>
   */
  private array $collectors = [];

  /**
   * {@inheritdoc}
   */
  public function addCollector(CollectorInterface $collector): void {
    $id = $collector->id();

    if (isset($this->collectors[$id])) {
      throw new \LogicException(sprintf('A collector with ID "%s" is already registered.', $id));
    }

    $this->collectors[$id] = $collector;
  }

  /**
   * {@inheritdoc}
   */
  public function get(string $id): CollectorInterface {
    if (!isset($this->collectors[$id])) {
      throw new \InvalidArgumentException(sprintf('Collector "%s" is not registered.', $id));
    }

    return $this->collectors[$id];
  }

  /**
   * {@inheritdoc}
   */
  public function all(): array {
    return $this->collectors;
  }

}
