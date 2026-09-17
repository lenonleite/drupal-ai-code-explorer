<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Model;

/**
 * Represents a service or service alias discovered in Drupal's container.
 */
final readonly class ServiceComponent extends Component {

  /**
   * Constructs a discovered service.
   *
   * @param string $id
   *   The service identifier.
   * @param string|null $className
   *   The configured PHP class, if known.
   * @param string|null $aliasTarget
   *   The target service identifier when this record is an alias.
   * @param array<string, list<string>> $references
   *   Referenced service IDs grouped by their definition context.
   */
  public function __construct(
    string $id,
    public ?string $className,
    public ?string $aliasTarget,
    public array $references,
  ) {
    parent::__construct(
      id: $id,
      type: 'service',
      label: $className ?? $id,
      sourcePath: NULL,
    );
  }

}
