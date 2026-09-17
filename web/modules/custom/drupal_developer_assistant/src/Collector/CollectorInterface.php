<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Collector;

/**
 * Defines a collector that discovers Drupal components.
 */
interface CollectorInterface {

  /**
   * Returns the collector's unique identifier.
   */
  public function id(): string;

  /**
   * Collects component records.
   *
   * @return list<\Drupal\drupal_developer_assistant\Model\Component>
   *   The discovered component records.
   */
  public function collect(): array;

}
