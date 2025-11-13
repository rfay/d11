<?php

declare(strict_types=1);

namespace Drupal\search_api_opensearch\Plugin\OpenSearch\Analyser;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api_opensearch\Analyser\AnalyserBase;
use Drupal\search_api_opensearch\Attribute\OpenSearchAnalyser;

/**
 * Defines an N-gram analyser.
 */
#[OpenSearchAnalyser(
  id: Ngram::PLUGIN_ID,
  label: new TranslatableMarkup("N-gram analyzer"),
)]
final class Ngram extends AnalyserBase {

  /**
   * The plugin ID.
   */
  public const PLUGIN_ID = 'ngram_analyzer';

  /**
   * The filter ID.
   */
  public const FILTER_ID = 'ngram_filter';

  /**
   * {@inheritdoc}
   */
  public function getSettings(): array {
    return [
      'analysis' => [
        'filter' => [
          self::FILTER_ID => [
            'type' => 'ngram',
            'min_gram' => 1,
            'max_gram' => 20,
          ],
        ],
        'analyzer' => [
          self::PLUGIN_ID => [
            'type' => 'custom',
            'tokenizer' => 'standard',
            'filter' => ['lowercase', 'asciifolding', self::FILTER_ID],
          ],
        ],
      ],
    ];
  }

}
