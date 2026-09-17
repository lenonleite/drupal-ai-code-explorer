<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Collector;

/**
 * Defines a registry of component collectors.
 */
interface CollectorManagerInterface {

  /**
   * Adds a collector to the registry.
   */
  public function addCollector(CollectorInterface $collector): void;

  /**
   * Returns a collector by its unique identifier.
   */
  public function get(string $id): CollectorInterface;

  /**
   * Returns all registered collectors, keyed by identifier.
   *
   * @return array<string, \Drupal\drupal_developer_assistant\Collector\CollectorInterface>
   *   The registered collectors.
   */
  public function all(): array;

}
