<?php

declare(strict_types=1);

namespace Drupal\drupal_developer_assistant\Architecture;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\drupal_developer_assistant\Model\ModuleArchitecture;

/**
 * Stores module architecture reports in a Drupal cache bin.
 */
final readonly class ModuleArchitectureCache implements ModuleArchitectureCacheInterface {

  /**
   * Cache-key format version for safe future changes.
   */
  private const string CACHE_VERSION = '1';

  /**
   * Constructs the module architecture cache.
   */
  public function __construct(
    private CacheBackendInterface $cache,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function get(string $module_id): ?ModuleArchitecture {
    $item = $this->cache->get($this->cacheId($module_id));
    if (!$item || !$item->data instanceof ModuleArchitecture) {
      return NULL;
    }

    return $item->data;
  }

  /**
   * {@inheritdoc}
   */
  public function set(
    string $module_id,
    ModuleArchitecture $architecture,
  ): void {
    $this->cache->set(
      $this->cacheId($module_id),
      $architecture,
      Cache::PERMANENT,
      [
        'config:core.extension',
        $this->cacheTag($module_id),
      ],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate(string $module_id): void {
    $this->cache->delete($this->cacheId($module_id));
  }

  /**
   * Builds the versioned cache identifier for one module.
   */
  private function cacheId(string $module_id): string {
    return implode(':', [
      'drupal_developer_assistant',
      'module_architecture',
      self::CACHE_VERSION,
      $module_id,
    ]);
  }

  /**
   * Builds the invalidation tag for one module.
   */
  private function cacheTag(string $module_id): string {
    return 'drupal_developer_assistant:module_architecture:' . $module_id;
  }

}
