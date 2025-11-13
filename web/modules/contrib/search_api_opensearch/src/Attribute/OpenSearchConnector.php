<?php

namespace Drupal\search_api_opensearch\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the OpenSearch connector attribute.
 *
 * @see \Drupal\search_api_opensearch\Connector\ConnectorPluginManager
 * @see \Drupal\search_api_opensearch\Connector\OpenSearchConnectorInterface
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class OpenSearchConnector extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly TranslatableMarkup $description,
  ) {}

}
