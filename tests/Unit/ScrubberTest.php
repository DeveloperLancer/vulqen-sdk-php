<?php

declare(strict_types=1);

namespace Vulqen\Sdk\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vulqen\Sdk\Scrubber;

final class ScrubberTest extends TestCase
{
    #[DataProvider('messages')]
    public function testCzysciKomunikat(string $message, string $expected): void
    {
        self::assertSame($expected, Scrubber::scrub($message));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function messages(): array
    {
        return [
            'hasło po równa się' => ['login failed password=hunter2 for user', 'login failed password=[scrubbed] for user'],
            'token w JSON' => ['payload {"api_token": "abc123", "id": 5}', 'payload {"api_token": [scrubbed], "id": 5}'],
            'nagłówek bearer' => ['Authorization: Bearer eyJhbGciOi.x.y', 'Authorization: [scrubbed] [scrubbed]'],
            'sam bearer' => ['got Bearer eyJhbGciOi.x.y from proxy', 'got Bearer [scrubbed] from proxy'],
            'query string' => ['GET /cb?secret=s3cr3t&page=2', 'GET /cb?secret=[scrubbed]&page=2'],
            'dsn' => ['cannot connect to redis://default:pa55@cache:6379', 'cannot connect to redis://***@cache:6379'],
            'zwykły tekst zostaje' => ['order 42 missing', 'order 42 missing'],
        ];
    }

    public function testNaglowkiZListy17SaZamaskowane(): void
    {
        $headers = Scrubber::headers([
            'Authorization' => 'Bearer x',
            'cookie' => 'PHPSESSID=1',
            'Set-Cookie' => 'a=b',
            'Proxy-Authorization' => 'Basic y',
            'Accept' => 'application/json',
        ]);

        self::assertSame(
            ['Authorization' => '[scrubbed]', 'cookie' => '[scrubbed]', 'Set-Cookie' => '[scrubbed]', 'Proxy-Authorization' => '[scrubbed]', 'Accept' => 'application/json'],
            $headers,
        );
    }
}
