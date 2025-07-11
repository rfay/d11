<?php

declare(strict_types=1);

namespace Drupal\Tests\content_moderation\Functional;

use Drupal\Tests\workspaces\Functional\WorkspaceTestUtilities;
use Drupal\workspaces\Entity\Workspace;

/**
 * Tests Workspaces together with Content Moderation.
 *
 * @group content_moderation
 * @group workspaces
 */
class WorkspaceContentModerationIntegrationTest extends ModerationStateTestBase {

  use WorkspaceTestUtilities;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'workspaces'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions(): array {
    return array_merge($this->permissions, [
      'bypass node access',
      'administer workspaces',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser($this->getAdministratorPermissions());
    $this->drupalLogin($this->adminUser);

    // Enable moderation on Article node type.
    $this->createContentTypeFromUi('Article', 'article', TRUE);

    $this->setupWorkspaceSwitcherBlock();
    $this->createWorkspaceThroughUi('Stage', 'stage');
  }

  /**
   * Tests moderating nodes in a workspace.
   */
  public function testModerationInWorkspace(): void {
    $stage = Workspace::load('stage');
    $this->switchToWorkspace($stage);

    // Create two nodes, a published and a draft one.
    $this->drupalGet('node/add/article');
    $this->submitForm([
      'title[0][value]' => 'First article - published',
      'moderation_state[0][state]' => 'published',
    ], 'Save');
    $this->drupalGet('node/add/article');
    $this->submitForm([
      'title[0][value]' => 'Second article - draft',
      'moderation_state[0][state]' => 'draft',
    ], 'Save');

    $first_article = $this->drupalGetNodeByTitle('First article - published', TRUE);
    $this->assertEquals('published', $first_article->moderation_state->value);
    $this->assertTrue($first_article->isPublished());

    $second_article = $this->drupalGetNodeByTitle('Second article - draft', TRUE);
    $this->assertEquals('draft', $second_article->moderation_state->value);
    $this->assertFalse($second_article->isPublished());

    // Check that neither of them are published in Live.
    $this->switchToLive();
    $first_article = $this->drupalGetNodeByTitle('First article - published', TRUE);
    $this->assertFalse($first_article->isPublished());

    $second_article = $this->drupalGetNodeByTitle('Second article - draft', TRUE);
    $this->assertFalse($second_article->isPublished());

    // Switch back to Stage.
    $this->switchToWorkspace($stage);

    // Take the first node through various moderation states.
    $this->drupalGet('/node/1/edit');
    $this->assertEquals('Current state Published', $this->cssSelect('#edit-moderation-state-0-current')[0]->getText());

    $this->submitForm([
      'title[0][value]' => 'First article - draft',
      'moderation_state[0][state]' => 'draft',
    ], 'Save');

    $this->drupalGet('/node/1');
    $this->assertSession()->pageTextContains('First article - draft');

    $this->drupalGet('/node/1/edit');
    $this->assertEquals('Current state Draft', $this->cssSelect('#edit-moderation-state-0-current')[0]->getText());

    $this->submitForm([
      'title[0][value]' => 'First article - published',
      'moderation_state[0][state]' => 'published',
    ], 'Save');

    $this->drupalGet('/node/1/edit');
    $this->submitForm([
      'title[0][value]' => 'First article - archived',
      'moderation_state[0][state]' => 'archived',
    ], 'Save');

    $this->drupalGet('/node/1');
    $this->assertSession()->pageTextContains('First article - archived');

    // Get the second node to a default revision state and publish the
    // workspace.
    $this->drupalGet('/node/2/edit');
    $this->submitForm([
      'title[0][value]' => 'Second article - published',
      'moderation_state[0][state]' => 'published',
    ], 'Save');

    $stage->publish();

    // The admin user can see unpublished nodes.
    $this->drupalGet('/node/1');
    $this->assertSession()->pageTextContains('First article - archived');

    $this->drupalGet('/node/2');
    $this->assertSession()->pageTextContains('Second article - published');
  }

}
