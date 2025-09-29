<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder\Unit;

use Drupal\layout_builder\Routing\LayoutSectionStorageParamConverter;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\layout_builder\Routing\LayoutSectionStorageParamConverter.
 */
#[CoversClass(LayoutSectionStorageParamConverter::class)]
#[Group('layout_builder')]
class LayoutSectionStorageParamConverterTest extends UnitTestCase {

  /**
   * Tests convert.
   *
   * @legacy-covers ::convert
   */
  public function testConvert(): void {
    $section_storage_manager = $this->prophesize(SectionStorageManagerInterface::class);
    $converter = new LayoutSectionStorageParamConverter($section_storage_manager->reveal());

    $section_storage = $this->prophesize(SectionStorageInterface::class);

    $value = 'some_value';
    $definition = ['layout_builder_tempstore' => TRUE];
    $name = 'the_parameter_name';
    $defaults = ['section_storage_type' => 'my_type'];

    $section_storage_manager->hasDefinition('my_type')->willReturn(TRUE);
    $section_storage_manager->loadEmpty('my_type')->willReturn($section_storage->reveal());
    $section_storage->deriveContextsFromRoute($value, $definition, $name, $defaults)->willReturn([]);
    $section_storage_manager->load('my_type', [])->willReturn($section_storage->reveal());

    $result = $converter->convert($value, $definition, $name, $defaults);
    $this->assertSame($section_storage->reveal(), $result);
  }

  /**
   * Tests convert no type.
   *
   * @legacy-covers ::convert
   */
  public function testConvertNoType(): void {
    $section_storage_manager = $this->prophesize(SectionStorageManagerInterface::class);
    $converter = new LayoutSectionStorageParamConverter($section_storage_manager->reveal());

    $value = 'some_value';
    $definition = ['layout_builder_tempstore' => TRUE];
    $name = 'the_parameter_name';
    $defaults = ['section_storage_type' => NULL];

    $section_storage_manager->hasDefinition()->shouldNotBeCalled();
    $section_storage_manager->load()->shouldNotBeCalled();

    $result = $converter->convert($value, $definition, $name, $defaults);
    $this->assertNull($result);
  }

  /**
   * Tests convert invalid converter.
   *
   * @legacy-covers ::convert
   */
  public function testConvertInvalidConverter(): void {
    $section_storage_manager = $this->prophesize(SectionStorageManagerInterface::class);
    $converter = new LayoutSectionStorageParamConverter($section_storage_manager->reveal());

    $value = 'some_value';
    $definition = ['layout_builder_tempstore' => TRUE];
    $name = 'the_parameter_name';
    $defaults = ['section_storage_type' => 'invalid'];

    $section_storage_manager->hasDefinition('invalid')->willReturn(FALSE);
    $section_storage_manager->load()->shouldNotBeCalled();

    $result = $converter->convert($value, $definition, $name, $defaults);
    $this->assertNull($result);
  }

}
