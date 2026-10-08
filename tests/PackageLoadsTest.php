<?php

declare(strict_types=1);

namespace Vulqen\Sdk\Tests;

use PHPUnit\Framework\TestCase;
use Vulqen\Sdk\Version;

final class PackageLoadsTest extends TestCase
{
    public function testPaczkaSieLaduje(): void
    {
        self::assertSame('0.1.0', Version::NUMBER);
    }
}
