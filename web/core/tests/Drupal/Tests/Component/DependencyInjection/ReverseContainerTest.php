<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\DependencyInjection;

use Drupal\Component\DependencyInjection\ReverseContainer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the ReverseContainer class.
 *
 * The reverse container uses a static to maintain information across
 * container rebuilds.
 */
#[CoversClass(ReverseContainer::class)]
#[Group('DependencyInjection')]
#[RunTestsInSeparateProcesses]
class ReverseContainerTest extends TestCase {

  /**
   * @legacy-covers ::getId
   */
  public function testGetId(): void {
    $container = new ContainerBuilder();
    $service = new \stdClass();
    $container->set('bar', $service);

    $reverse_container = new ReverseContainer($container);

    $this->assertSame('bar', $reverse_container->getId($service));
    $non_service = new \stdClass();
    $this->assertNull($reverse_container->getId($non_service));
    $this->assertSame('service_container', $reverse_container->getId($container));
  }

  /**
   * @legacy-covers ::recordContainer
   */
  public function testRecordContainer(): void {
    $container = new ContainerBuilder();
    $service = new \stdClass();
    $container->set('bar', $service);

    $reverse_container = new ReverseContainer($container);
    $reverse_container->recordContainer();

    $container = new ContainerBuilder();
    $reverse_container = new ReverseContainer($container);

    // New container does not have a bar service.
    $this->assertNull($reverse_container->getId($service));

    // Add the bar service to make the lookup based on the old object work as
    // expected.
    $container->set('bar', new \stdClass());
    $this->assertSame('bar', $reverse_container->getId($service));
  }

}
