<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Validation;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Validation\ConstraintFactory;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Validator\Constraint;

/**
 * Tests Drupal\Core\Validation\ConstraintFactory.
 */
#[CoversClass(ConstraintFactory::class)]
#[Group('Validation')]
class ConstraintFactoryTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_test'];

  /**
   * Tests create instance.
   *
   * @legacy-covers ::createInstance
   */
  public function testCreateInstance(): void {
    $constraint_manager = \Drupal::typedDataManager()->getValidationConstraintManager();

    // If the plugin is a \Symfony\Component\Validator\Constraint, they will be
    // created first.
    $this->assertInstanceOf(Constraint::class, $constraint_manager->create('Uuid', []));

    // If the plugin implements the
    // \Drupal\Core\Plugin\ContainerFactoryPluginInterface, they will be created
    // second.
    $container_factory_plugin = $constraint_manager->create('EntityTestContainerFactoryPlugin', []);
    $this->assertNotInstanceOf(Constraint::class, $container_factory_plugin);
    $this->assertInstanceOf(ContainerFactoryPluginInterface::class, $container_factory_plugin);

    // Plugins that are not a \Symfony\Component\Validator\Constraint or do not
    // implement the \Drupal\Core\Plugin\ContainerFactoryPluginInterface are
    // created last.
    $default_plugin = $constraint_manager->create('EntityTestDefaultPlugin', []);
    $this->assertNotInstanceOf(Constraint::class, $default_plugin);
    $this->assertNotInstanceOf(ContainerFactoryPluginInterface::class, $default_plugin);
    $this->assertInstanceOf(PluginBase::class, $default_plugin);
  }

}
