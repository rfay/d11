<?php

declare(strict_types=1);

namespace Drupal\search_api_opensearch\Connector;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\search_api_opensearch\Annotation\OpenSearchConnector as OpenSearchConnectorAnnotation;
use Drupal\search_api_opensearch\Attribute\OpenSearchConnector as OpenSearchConnectorAttribute;

/**
 * A plugin manager for OpenSearch connector plugins.
 *
 * @see \Drupal\search_api_opensearch\Annotation\OpenSearchConnector
 * @see \Drupal\search_api_opensearch\Connector\OpenSearchConnectorInterface
 *
 * @ingroup plugin_api
 */
class ConnectorPluginManager extends DefaultPluginManager {

  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    $this->alterInfo('opensearch_connector_info');
    $this->setCacheBackend($cache_backend, 'opensearch_connector_plugins');

    parent::__construct(
      'Plugin/OpenSearch/Connector',
      $namespaces,
      $module_handler,
      OpenSearchConnectorInterface::class,
      OpenSearchConnectorAttribute::class,
      OpenSearchConnectorAnnotation::class,
    );
  }

}
