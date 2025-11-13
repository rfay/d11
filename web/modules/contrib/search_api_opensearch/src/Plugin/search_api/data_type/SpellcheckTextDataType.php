<?php

declare(strict_types=1);

namespace Drupal\search_api_opensearch\Plugin\search_api\data_type;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiDataType;
use Drupal\search_api\Plugin\search_api\data_type\TextDataType;

/**
 * Provides data type to feed the suggester component.
 */
#[SearchApiDataType(
  id: "search_api_opensearch_text_spellcheck",
  label: new TranslatableMarkup("OpenSearch Spellcheck"),
  description: new TranslatableMarkup("Full text field to feed the spellcheck component."),
  fallback_type: "text",
)]
class SpellcheckTextDataType extends TextDataType {}
