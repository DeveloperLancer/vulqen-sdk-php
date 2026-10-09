<?php

declare(strict_types=1);

namespace Vulqen\Sdk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vulqen\Sdk\Ids;
use Vulqen\Sdk\Timestamp;

final class IdsTest extends TestCase
{
    public function testUuid7MaWersjeIWariant(): void
    {
        $id = Ids::uuid7(1_791_000_000.123);

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id);
        self::assertSame(str_pad(dechex(1_791_000_000_123), 12, '0', STR_PAD_LEFT), str_replace('-', '', substr($id, 0, 13)));
    }

    public function testUuid7RosnieZCzasem(): void
    {
        self::assertLessThan(Ids::uuid7(1_791_000_000.002), Ids::uuid7(1_791_000_000.001));
    }

    public function testIdentyfikatoryŚladu(): void
    {
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', Ids::traceId());
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', Ids::spanId());
        self::assertNotSame(Ids::spanId(), Ids::spanId());
    }

    public function testCzasWUtcZMilisekundami(): void
    {
        self::assertSame('2026-10-08T12:22:01.123Z', Timestamp::format(1_791_462_121.1234));
        self::assertSame('2026-10-08T12:22:01.000Z', Timestamp::format(1_791_462_121.0));
    }
}
