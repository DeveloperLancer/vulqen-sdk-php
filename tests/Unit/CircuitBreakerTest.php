<?php

declare(strict_types=1);

namespace Vulqen\Sdk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vulqen\Sdk\CircuitBreaker;
use Vulqen\Sdk\ExceptionLimiter;
use Vulqen\Sdk\State\FileStore;

final class CircuitBreakerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/vulqen-sdk-test-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testTrzyPorazkiOtwierajaObwodNa30Sekund(): void
    {
        $breaker = new CircuitBreaker(new FileStore($this->directory), 'http://ingest/x');
        $now = 1_000.0;

        $breaker->recordFailure($now);
        $breaker->recordFailure($now);
        self::assertFalse($breaker->isOpen($now));

        $breaker->recordFailure($now);
        self::assertTrue($breaker->isOpen($now + 29));
        self::assertFalse($breaker->isOpen($now + 31));
    }

    public function testSukcesZerujeLicznik(): void
    {
        $breaker = new CircuitBreaker(new FileStore($this->directory), 'http://ingest/x');

        $breaker->recordFailure(1_000.0);
        $breaker->recordFailure(1_000.0);
        $breaker->recordSuccess();
        $breaker->recordFailure(1_000.0);

        self::assertFalse($breaker->isOpen(1_000.0));
    }

    public function testStanJestWspoldzielonyPrzezPlik(): void
    {
        (new CircuitBreaker(new FileStore($this->directory), 'http://ingest/x'))->openFor(10, 1_000.0);

        self::assertTrue((new CircuitBreaker(new FileStore($this->directory), 'http://ingest/x'))->isOpen(1_005.0));
        self::assertFalse((new CircuitBreaker(new FileStore($this->directory), 'http://ingest/inny'))->isOpen(1_005.0));
    }

    public function testRetryAfterMaksymalnie60Sekund(): void
    {
        $breaker = new CircuitBreaker(new FileStore($this->directory), 'http://ingest/x');

        $breaker->openFor(3600, 1_000.0);
        self::assertTrue($breaker->isOpen(1_059.0));
        self::assertFalse($breaker->isOpen(1_061.0));

        $breaker->openFor(null, 2_000.0);
        self::assertTrue($breaker->isOpen(2_059.0));
    }

    public function testNieczytelnyKatalogNieRzuca(): void
    {
        $breaker = new CircuitBreaker(new FileStore('/dev/null/vulqen'), 'http://ingest/x');
        $breaker->recordFailure();

        self::assertFalse($breaker->isOpen());
    }

    public function testLimitWyjatkowNaOdcisk(): void
    {
        $limiter = new ExceptionLimiter();
        $same = new \RuntimeException('a');
        $allowed = 0;
        for ($i = 0; $i < 25; ++$i) {
            $allowed += $limiter->allow($same, 1_000.0) ? 1 : 0;
        }

        self::assertSame(ExceptionLimiter::LIMIT, $allowed);
        self::assertTrue($limiter->allow(new \LogicException('inny odcisk'), 1_000.0));
        self::assertTrue($limiter->allow($same, 1_061.0));
    }
}
