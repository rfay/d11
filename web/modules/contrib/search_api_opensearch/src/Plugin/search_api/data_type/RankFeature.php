<?php

declare(strict_types=1);

namespace Drupal\search_api_opensearch\Plugin\search_api\data_type;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiDataType;
use Drupal\search_api\Plugin\search_api\data_type\DecimalDataType;

/**
 * Defines a class for a rank feature data-type.
 */
#[SearchApiDataType(
  id: "search_api_opensearch_rank_feature",
  label: new TranslatableMarkup("Rank feature"),
  description: new TranslatableMarkup("Rank feature"),
  fallback_type: "decimal",
)]
final class RankFeature extends DecimalDataType {

}
