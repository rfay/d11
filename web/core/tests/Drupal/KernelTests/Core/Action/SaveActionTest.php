<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Action;

use Drupal\Core\Action\Plugin\Action\Derivative\EntityChangedActionDeriver;
use Drupal\entity_test\Entity\EntityTestMulChanged;
use Drupal\KernelTests\KernelTestBase;
use Drupal\system\Entity\Action;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Save Action.
 */
#[Group('Action')]
class SaveActionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'entity_test', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test_mul_changed');
  }

  /**
   * Tests get derivative definitions.
   *
   * @legacy-covers \Drupal\Core\Action\Plugin\Action\Derivative\EntityChangedActionDeriver::getDerivativeDefinitions
   */
  public function testGetDerivativeDefinitions(): void {
    $deriver = new EntityChangedActionDeriver(\Drupal::entityTypeManager(), \Drupal::translation());
    $definitions = $deriver->getDerivativeDefinitions([
      'action_label' => 'Save',
    ]);
    $this->assertEquals([
      'type' => 'entity_test_mul_changed',
      'label' => 'Save test entity - multiple changed and data table',
      'action_label' => 'Save',
    ], $definitions['entity_test_mul_changed']);
  }

  /**
   * Tests save action.
   *
   * @legacy-covers \Drupal\Core\Action\Plugin\Action\SaveAction::execute
   */
  public function testSaveAction(): void {
    $entity = EntityTestMulChanged::create(['name' => 'test']);
    $entity->save();
    $saved_time = $entity->getChangedTime();

    $action = Action::create([
      'id' => 'entity_save_action',
      'plugin' => 'entity:save_action:entity_test_mul_changed',
    ]);
    $action->save();
    $action->execute([$entity]);
    $this->assertNotSame($saved_time, $entity->getChangedTime());
    $this->assertSame(['module' => ['entity_test']], $action->getDependencies());
  }

}
