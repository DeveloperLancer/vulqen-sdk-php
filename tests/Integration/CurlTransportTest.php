<?php

declare(strict_types=1);

namespace Vulqen\Sdk\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Vulqen\Sdk\Dsn;
use Vulqen\Sdk\Transport\CurlTransport;

/**
 * Prawdziwy curl na php -S z routerem udającym ingest (tests/Support/ingest-router.php).
 */
final class CurlTransportTest extends TestCase
{
    private const PROJECT = '01926b3a-7c2e-7a1b-8f3e-1c2d3e4f';

    /** @var resource|null */
    private static $server = null;
    private static int $port = 0;

    public static function setUpBeforeClass(): void
    {
        self::$port = self::freePort();
        $router = dirname(__DIR__).'/Support/ingest-router.php';
        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:'.self::$port, $router],
            [0 => ['pipe', 'r'], 1 => ['file', self::nullDevice(), 'w'], 2 => ['file', self::nullDevice(), 'w']],
            $pipes,
        );
        if (!is_resource($process)) {
            self::fail('Nie udało się uruchomić php -S.');
        }
        self::$server = $process;

        $deadline = microtime(true) + 5;
        while (microtime(true) < $deadline) {
            $socket = @fsockopen('127.0.0.1', self::$port, $errno, $errstr, 0.1);
            if (is_resource($socket)) {
                fclose($socket);

                return;
            }
            usleep(50_000);
        }
        self::fail('php -S nie wstał na porcie '.self::$port);
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
        @unlink(self::log());
    }

    protected function setUp(): void
    {
        @unlink(self::log());
    }

    public function test202ZwracaConfigIWysylaGzipZKluczemWNaglowku(): void
    {
        $result = (new CurlTransport())->send($this->dsn('0202'), '{"v":1,"items":[]}');

        self::assertSame(202, $result->status);
        self::assertSame(7, $result->config['max_spans'] ?? null);

        $requests = self::requests();
        self::assertCount(1, $requests);
        self::assertSame('vq_test_sekret', $requests[0]['key']);
        self::assertSame('gzip', $requests[0]['encoding']);
        self::assertSame('{"v":1,"items":[]}', $requests[0]['body']);
    }

    public function test429NiesieRetryAfterBezPonowienia(): void
    {
        $result = (new CurlTransport())->send($this->dsn('0429'), '{}');

        self::assertTrue($result->rateLimited());
        self::assertSame(7, $result->retryAfter);
        self::assertCount(1, self::requests());
    }

    public function test500NieJestPonawiane(): void
    {
        $result = (new CurlTransport())->send($this->dsn('0500'), '{}');

        self::assertSame(500, $result->status);
        self::assertTrue($result->serverFailed());
        self::assertCount(1, self::requests());
    }

    public function testTimeoutDajeStatusZeroIJednoPonowienie(): void
    {
        $silent = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($silent);
        $address = (string) stream_socket_get_name($silent, false);

        $started = microtime(true);
        $result = (new CurlTransport(200, 300))->send(
            Dsn::parse('http://vq_test_sekret@'.$address.'/'.self::PROJECT.'0202') ?? self::fail('DSN'),
            '{}',
        );
        $elapsed = microtime(true) - $started;
        fclose($silent);

        self::assertSame(0, $result->status);
        self::assertGreaterThanOrEqual(0.55, $elapsed, 'Dwie próby po 300 ms, czyli jedno ponowienie.');
        self::assertLessThan(1.5, $elapsed);
    }

    public function testZamknietyPortKonczySieSzybko(): void
    {
        $started = microtime(true);
        $result = (new CurlTransport())->send(
            Dsn::parse('http://vq_test_sekret@127.0.0.1:'.self::freePort().'/'.self::PROJECT.'0202') ?? self::fail('DSN'),
            '{}',
        );

        self::assertSame(0, $result->status);
        self::assertNotNull($result->error);
        self::assertLessThan(1.0, microtime(true) - $started);
    }

    private function dsn(string $mode): Dsn
    {
        return Dsn::parse(sprintf('http://vq_test_sekret@127.0.0.1:%d/%s%s', self::$port, self::PROJECT, $mode)) ?? self::fail('DSN');
    }

    /**
     * @return list<array{mode: string, key: ?string, encoding: ?string, body: string}>
     */
    private static function requests(): array
    {
        $lines = @file(self::log(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        return array_values(array_map(static fn (string $line): array => json_decode($line, true), $lines));
    }

    private static function log(): string
    {
        return sys_get_temp_dir().'/vulqen-router-'.self::$port.'.jsonl';
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if ($socket === false) {
            self::fail('Brak wolnego portu.');
        }
        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, strrpos($name, ':') + 1);
    }

    private static function nullDevice(): string
    {
        return DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
    }
}
