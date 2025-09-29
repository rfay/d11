<?php

declare(strict_types=1);

namespace Drupal\Tests\locale\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests locale translation project handling.
 */
#[Group('locale')]
class LocaleTranslationProjectsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['locale', 'locale_test', 'system'];

  /**
   * The module handler used in this test.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The locale project storage used in this test.
   *
   * @var \Drupal\locale\LocaleProjectStorageInterface
   */
  protected $projectStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->moduleHandler = $this->container->get('module_handler');
    $this->projectStorage = $this->container->get('locale.project');
    \Drupal::state()->set('locale.remove_core_project', TRUE);
  }

  /**
   * Tests locale_translation_clear_cache_projects().
   */
  public function testLocaleTranslationClearCacheProjects(): void {
    $this->moduleHandler->loadInclude('locale', 'inc', 'locale.translation');

    $expected = [];
    $this->assertSame($expected, locale_translation_get_projects());

    $this->projectStorage->set('foo', []);
    $expected['foo'] = new \stdClass();
    $this->assertEquals($expected, locale_translation_get_projects());

    $this->projectStorage->set('bar', []);
    locale_translation_clear_cache_projects();
    $expected['bar'] = new \stdClass();
    $this->assertEquals($expected, locale_translation_get_projects());
  }

}
