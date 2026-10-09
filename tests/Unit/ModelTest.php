<?php

declare(strict_types=1);

namespace Vulqen\Sdk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vulqen\Sdk\Model\ExceptionData;
use Vulqen\Sdk\Model\Frame;
use Vulqen\Sdk\Model\Transaction;

final class ModelTest extends TestCase
{
    public function testLimitSpanowUstawiaSpansTruncated(): void
    {
        $transaction = new Transaction('app_order_show', Transaction::OP_HTTP, 1_791_462_121.0, 2);

        self::assertNotNull($transaction->startSpan('db.query', 'SELECT ?'));
        self::assertNotNull($transaction->startSpan('db.query', 'SELECT ?'));
        self::assertNull($transaction->startSpan('db.query', 'SELECT ?'));

        $transaction->setHttpStatus(200);
        $transaction->finish(1_791_462_121.5);
        $item = $transaction->toArray();

        self::assertTrue($item['spans_truncated']);
        self::assertCount(2, $item['spans']);
        self::assertSame(500.0, $item['duration_ms']);
        self::assertSame(200, $item['http_status']);
    }

    public function testNiezamknietySpanKonczySieZTransakcja(): void
    {
        $transaction = new Transaction('t', Transaction::OP_HTTP, 1_791_462_121.0, 10);
        $span = $transaction->startSpan('db.query', 'SELECT ?', [], 1_791_462_121.1);
        $transaction->finish(1_791_462_121.3);

        self::assertNotNull($span);
        self::assertTrue($span->isFinished());
        self::assertSame(200.0, $span->toArray()['duration_ms']);
        self::assertNull($transaction->startSpan('db.query', 'po końcu'));
    }

    public function testHttpStatusTylkoPrzyHttpServer(): void
    {
        $command = new Transaction('app:sync', 'console.command', 1_791_462_121.0, 10);
        $command->finish(1_791_462_122.0);

        self::assertArrayNotHasKey('http_status', $command->toArray());

        $http = new Transaction('app_x', Transaction::OP_HTTP, 1_791_462_121.0, 10);
        $http->finish(1_791_462_122.0);

        self::assertSame(500, $http->toArray()['http_status']);
    }

    public function testPustyAttrsJestObiektemJson(): void
    {
        $transaction = new Transaction('t', Transaction::OP_HTTP, 1_791_462_121.0, 10);
        $transaction->startSpan('db.query', 'SELECT ?');
        $transaction->finish(1_791_462_122.0);

        self::assertStringContainsString('"attrs":{}', (string) json_encode($transaction->toArray()));
    }

    public function testKlatkiOdMiejscaRzucenia(): void
    {
        $exception = $this->throwInApp();
        $data = ExceptionData::fromThrowable($exception, dirname(__DIR__, 2));
        $first = $data->frames[0];

        self::assertSame(\RuntimeException::class, $data->class);
        self::assertSame('tests/Unit/ModelTest.php', $first->file);
        self::assertSame(self::class, $first->class);
        self::assertSame('throwInApp', $first->function);
        self::assertTrue($first->inApp);
        self::assertSame($exception->getLine(), $first->line);
    }

    public function testKlatkaVendorNieJestInApp(): void
    {
        self::assertFalse(Frame::at('/app/vendor/symfony/http-kernel/Kernel.php', 10, null, '/app')->inApp);
        self::assertFalse(Frame::at('C:\\app\\vendor\\x\\A.php', 10, null, 'C:\\app')->inApp);
        self::assertSame('vendor/x/A.php', Frame::at('C:\\app\\vendor\\x\\A.php', 10, null, 'C:\\app')->file);
        self::assertTrue(Frame::at('/app/src/A.php', 10, null, '/app')->inApp);
        self::assertSame(Frame::INTERNAL, Frame::at(null, 0, ['function' => 'array_map'], '/app')->file);
    }

    public function testPreviousDoGlebokosciPieciuIScrubbing(): void
    {
        $exception = new \LogicException('level 0 password=x');
        for ($level = 1; $level <= 8; ++$level) {
            $exception = new \RuntimeException('level '.$level, 0, $exception);
        }

        $data = ExceptionData::fromThrowable($exception, null);
        $depth = 0;
        $current = $data->previous;
        while ($current !== null) {
            ++$depth;
            $current = $current->previous;
        }

        self::assertSame(ExceptionData::MAX_PREVIOUS, $depth);
        self::assertStringContainsString('password=[scrubbed]', ExceptionData::fromThrowable(new \LogicException('password=x'), null)->message);
    }

    private function throwInApp(): \RuntimeException
    {
        try {
            throw new \RuntimeException('order 42 missing');
        } catch (\RuntimeException $e) {
            return $e;
        }
    }
}
