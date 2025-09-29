<?php

declare(strict_types=1);

namespace Drupal\Tests\language\Kernel\Plugin\migrate\source;

use Drupal\language\Plugin\migrate\source\Language;
use Drupal\Tests\migrate\Kernel\MigrateSqlSourceTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the language source plugin.
 */
#[CoversClass(Language::class)]
#[Group('language')]
class LanguageTest extends MigrateSqlSourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['language', 'migrate_drupal'];

  /**
   * {@inheritdoc}
   */
  public static function providerSource() {
    $tests = [];

    // The source data.
    $tests[0]['source_data']['languages'] = [
      [
        'language' => 'en',
        'name' => 'English',
        'native' => 'English',
        'direction' => '0',
        'enabled' => '1',
        'plurals' => '0',
        'formula' => '',
        'domain' => '',
        'prefix' => '',
        'weight' => '0',
        'javascript' => '',
      ],
      [
        'language' => 'fr',
        'name' => 'French',
        'native' => 'Français',
        'direction' => '0',
        'enabled' => '0',
        'plurals' => '2',
        'formula' => '($n>1)',
        'domain' => '',
        'prefix' => 'fr',
        'weight' => '0',
        'javascript' => '',
      ],
    ];

    // The expected results.
    $tests[0]['expected_data'] = [
      [
        'language' => 'en',
        'name' => 'English',
        'native' => 'English',
        'direction' => '0',
        'enabled' => '1',
        'plurals' => '0',
        'formula' => '',
        'domain' => '',
        'prefix' => '',
        'weight' => '0',
        'javascript' => '',
      ],
      [
        'language' => 'fr',
        'name' => 'French',
        'native' => 'Français',
        'direction' => '0',
        'enabled' => '0',
        'plurals' => '2',
        'formula' => '($n>1)',
        'domain' => '',
        'prefix' => 'fr',
        'weight' => '0',
        'javascript' => '',
      ],
    ];

    return $tests;
  }

}
