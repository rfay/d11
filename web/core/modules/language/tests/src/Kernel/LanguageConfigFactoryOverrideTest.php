<?php

declare(strict_types=1);

namespace Drupal\Tests\language\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests \Drupal\language\Config\LanguageConfigFactoryOverride.
 */
#[Group('language')]
class LanguageConfigFactoryOverrideTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'language'];

  /**
   * Tests language.config_factory_override service has the default language.
   */
  public function testLanguageConfigFactoryOverride(): void {
    $this->installConfig('system');
    $this->installConfig('language');

    /** @var \Drupal\language\Config\LanguageConfigFactoryOverride $config_factory_override */
    $config_factory_override = \Drupal::service('language.config_factory_override');
    $this->assertEquals('en', $config_factory_override->getLanguage()->getId());

    ConfigurableLanguage::createFromLangcode('de')->save();

    // Invalidate the container.
    $this->config('system.site')->set('default_langcode', 'de')->save();
    $this->container->get('kernel')->rebuildContainer();

    $config_factory_override = \Drupal::service('language.config_factory_override');
    $this->assertEquals('de', $config_factory_override->getLanguage()->getId());
  }

}
