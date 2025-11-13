<?php

declare(strict_types=1);

namespace Drupal\search_api_opensearch\Analyser;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\search_api_opensearch\Annotation\OpenSearchAnalyser as OpenSearchAnalyserAnnotation;
use Drupal\search_api_opensearch\Attribute\OpenSearchAnalyser as OpenSearchAnalyserAttribute;

/**
 * Defines a plugin manager for analyser plugins.
 */
final class AnalyserManager extends DefaultPluginManager {

  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    $this->alterInfo('opensearch_analyser_info');
    $this->setCacheBackend($cache_backend, 'opensearch_analyser_plugins');

    parent::__construct(
      'Plugin/OpenSearch/Analyser',
      $namespaces,
      $module_handler,
      AnalyserInterface::class,
      OpenSearchAnalyserAttribute::class,
      OpenSearchAnalyserAnnotation::class
    );
  }

}
