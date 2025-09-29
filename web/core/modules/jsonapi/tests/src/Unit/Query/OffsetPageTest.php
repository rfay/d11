<?php

declare(strict_types=1);

namespace Drupal\Tests\jsonapi\Unit\Query;

use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\DependencyInjection\Container;
use Drupal\jsonapi\Query\OffsetPage;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Tests Drupal\jsonapi\Query\OffsetPage.
 *
 * @internal
 */
#[CoversClass(OffsetPage::class)]
#[Group('jsonapi')]
class OffsetPageTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new Container();
    $cache_context_manager = $this->prophesize(CacheContextsManager::class);
    $cache_context_manager->assertValidTokens(Argument::any())
      ->willReturn(TRUE);
    $container->set('cache_contexts_manager', $cache_context_manager->reveal());
    \Drupal::setContainer($container);
  }

  /**
   * Tests create from query parameter.
   *
   * @legacy-covers ::createFromQueryParameter
   */
  #[DataProvider('parameterProvider')]
  public function testCreateFromQueryParameter($original, $expected): void {
    $actual = OffsetPage::createFromQueryParameter($original);
    $this->assertEquals($expected['offset'], $actual->getOffset());
    $this->assertEquals($expected['limit'], $actual->getSize());
  }

  /**
   * Data provider for testCreateFromQueryParameter.
   */
  public static function parameterProvider() {
    return [
      [['offset' => 12, 'limit' => 20], ['offset' => 12, 'limit' => 20]],
      [['offset' => 12, 'limit' => 60], ['offset' => 12, 'limit' => 50]],
      [['offset' => 12], ['offset' => 12, 'limit' => 50]],
      [['offset' => 0], ['offset' => 0, 'limit' => 50]],
      [[], ['offset' => 0, 'limit' => 50]],
    ];
  }

  /**
   * Tests create from query parameter fail.
   *
   * @legacy-covers ::createFromQueryParameter
   */
  public function testCreateFromQueryParameterFail(): void {
    $this->expectException(BadRequestHttpException::class);
    OffsetPage::createFromQueryParameter('lorem');
  }

}
