<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Render\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Textfield;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\Render\Element\Textfield.
 */
#[CoversClass(Textfield::class)]
#[Group('Render')]
class TextfieldTest extends UnitTestCase {

  /**
   * Tests value callback.
   *
   * @legacy-covers ::valueCallback
   */
  #[DataProvider('providerTestValueCallback')]
  public function testValueCallback($expected, $input): void {
    $element = [];
    $form_state = $this->prophesize(FormStateInterface::class)->reveal();
    $this->assertSame($expected, Textfield::valueCallback($element, $input, $form_state));
  }

  /**
   * Data provider for testValueCallback().
   */
  public static function providerTestValueCallback() {
    $data = [];
    $data[] = [NULL, FALSE];
    $data[] = [NULL, NULL];
    $data[] = ['', ['test']];
    $data[] = ['test', 'test'];
    $data[] = ['123', 123];
    $data[] = ['testWithNewline', "test\nWith\rNewline"];

    return $data;
  }

}
