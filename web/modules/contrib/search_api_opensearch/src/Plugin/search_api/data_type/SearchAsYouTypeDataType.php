<?php

declare(strict_types=1);

namespace Drupal\search_api_opensearch\Plugin\search_api\data_type;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiDataType;
use Drupal\search_api\Plugin\search_api\data_type\TextDataType;

/**
 * Defines a class for a search-as-you-type data type.
 */
#[SearchApiDataType(
  id: "search_api_opensearch_search_as_you_type",
  label: new TranslatableMarkup("Search-As-You-Type"),
  description: new TranslatableMarkup("search-as-you-type"),
  fallback_type: "text",
)]
final class SearchAsYouTypeDataType extends TextDataType {

}
