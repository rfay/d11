<?php

declare(strict_types=1);

namespace Drupal\search_api_opensearch\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an OpenSearch analyzer plugin attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class OpenSearchAnalyser extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
  ) {}

}
