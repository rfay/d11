<?php

declare(strict_types=1);

namespace Drupal\Tests\pgsql\Kernel\pgsql;

use Drupal\KernelTests\Core\Database\DriverSpecificKernelTestBase;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\KernelTests\KernelTestBase.
 */
#[CoversClass(KernelTestBase::class)]
#[Group('KernelTests')]
#[Group('Database')]
class KernelTestBaseTest extends DriverSpecificKernelTestBase {

  /**
   * Tests set up.
   *
   * @legacy-covers ::setUp
   */
  public function testSetUp(): void {
    // Ensure that the database tasks have been run during set up.
    $this->assertSame('on', $this->connection->query("SHOW standard_conforming_strings")->fetchField());
    $this->assertSame('escape', $this->connection->query("SHOW bytea_output")->fetchField());
  }

}
