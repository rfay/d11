<?php

declare(strict_types=1);

namespace Drupal\Tests\image\Kernel;

use Drupal\image\Entity\ImageStyle;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests config import for Image styles.
 */
#[Group('image')]
class ImageImportTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'image', 'image_module_test'];

  /**
   * Tests importing image styles.
   */
  public function testImport(): void {
    $style = ImageStyle::create([
      'name' => 'test',
      'label' => 'Test',
    ]);

    $style->addImageEffect(['id' => 'image_module_test_null', 'weight' => 0]);
    $style->addImageEffect(['id' => 'image_module_test_null', 'weight' => 1]);
    $style->save();

    $this->assertCount(2, $style->getEffects());

    $uuid = \Drupal::service('uuid')->generate();
    $style->set('effects', [
      $uuid => [
        'id' => 'image_module_test_null',
        'weight' => 0,
      ],
    ]);
    $style->save();

    $style = ImageStyle::load('test');
    $this->assertCount(1, $style->getEffects());
  }

}
