<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Kernel\Views;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Contains tests related to the views data for the user entity type.
 *
 * @see \Drupal\user\UserViewsData
 */
#[Group('user')]
class UserViewsDataTest extends KernelTestBase {

  /**
   * The views data service.
   *
   * @var \Drupal\views\ViewsData
   */
  protected $viewsData;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->viewsData = $this->container->get('views.views_data');
    $this->entityFieldManager = $this->container->get('entity_field.manager');
  }

  /**
   * Tests if user views data object doesn't contain pass field.
   */
  public function testUserPasswordFieldNotAvailableToViews(): void {
    $field_definitions = $this->entityFieldManager->getBaseFieldDefinitions('user');
    $this->assertArrayHasKey('pass', $field_definitions);
    $this->assertArrayNotHasKey('pass', $this->viewsData->get('users_field_data'));
  }

}
