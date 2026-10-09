<?php

declare(strict_types=1);

namespace Vulqen\Sdk\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vulqen\Sdk\Dsn;

final class DsnTest extends TestCase
{
    public function testSkladaUrlIKlucz(): void
    {
        $dsn = Dsn::parse('https://vq_live_ab12cd34_sekret@Ingest.Vulqen.example/01926B3A-7C2E-7A1B-8F3E-1C2D3E4F5A6B');

        self::assertNotNull($dsn);
        self::assertSame('https://ingest.vulqen.example/api/v1/projects/01926b3a-7c2e-7a1b-8f3e-1c2d3e4f5a6b/envelope', $dsn->endpoint);
        self::assertSame('vq_live_ab12cd34_sekret', $dsn->key);
        self::assertSame('ingest.vulqen.example', $dsn->host);
        self::assertStringNotContainsString($dsn->key, $dsn->endpoint);
    }

    public function testZachowujePort(): void
    {
        self::assertSame(
            'http://ingest.localhost:8100/api/v1/projects/01926b3a-7c2e-7a1b-8f3e-1c2d3e4f5a6b/envelope',
            Dsn::parse('http://vq_test_k@ingest.localhost:8100/01926b3a-7c2e-7a1b-8f3e-1c2d3e4f5a6b')?->endpoint,
        );
    }

    public function testPustyDsnToNull(): void
    {
        self::assertNull(Dsn::parse(''));
        self::assertNull(Dsn::parse('  '));
        self::assertNull(Dsn::parse(null));
    }

    #[DataProvider('invalid')]
    public function testBlednyDsnRzuca(string $dsn): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Dsn::parse($dsn);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalid(): array
    {
        return [
            'bez klucza' => ['https://ingest.vulqen.example/01926b3a-7c2e-7a1b-8f3e-1c2d3e4f5a6b'],
            'bez projektu' => ['https://key@ingest.vulqen.example/'],
            'projekt nie uuid' => ['https://key@ingest.vulqen.example/abc'],
            'zły schemat' => ['ftp://key@ingest.vulqen.example/01926b3a-7c2e-7a1b-8f3e-1c2d3e4f5a6b'],
            'śmieci' => ['nie dsn'],
        ];
    }
}
