<?php

declare(strict_types=1);

namespace Drupal\search_api_opensearch\Plugin\search_api\data_type;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiDataType;
use Drupal\search_api\Plugin\search_api\data_type\TextDataType;

/**
 * Defines a class for n-gram data type.
 */
#[SearchApiDataType(
  id: "search_api_opensearch_ngram",
  label: new TranslatableMarkup("N-gram"),
  description: new TranslatableMarkup("ngram"),
  fallback_type: "text",
)]
final class NgramDataType extends TextDataType {

}
