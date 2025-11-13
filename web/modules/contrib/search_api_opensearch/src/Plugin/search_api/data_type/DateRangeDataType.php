<?php

declare(strict_types=1);

namespace Drupal\search_api_opensearch\Plugin\search_api\data_type;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiDataType;
use Drupal\search_api\Plugin\search_api\data_type\DateDataType;

/**
 * Provides a date range data type.
 */
#[SearchApiDataType(
  id: "search_api_opensearch_date_range",
  label: new TranslatableMarkup("Date range"),
  description: new TranslatableMarkup("Date field that contains date ranges."),
  fallback_type: "date",
)]
class DateRangeDataType extends DateDataType {

}
