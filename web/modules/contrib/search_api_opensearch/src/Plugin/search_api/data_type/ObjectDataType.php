<?php

declare(strict_types=1);

namespace Drupal\search_api_opensearch\Plugin\search_api\data_type;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiDataType;
use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a structured object data type.
 */
#[SearchApiDataType(
  id: "object",
  label: new TranslatableMarkup("Object"),
  description: new TranslatableMarkup("Structured Object support"),
  default: TRUE,
)]
class ObjectDataType extends DataTypePluginBase {

}
