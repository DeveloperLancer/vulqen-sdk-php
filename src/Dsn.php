<?php

declare(strict_types=1);

namespace Vulqen\Sdk;

/**
 * DSN z dashboardu: vulqen://klucz@host[:port]/<projectId>[?insecure] (20, D-076).
 * Brak query otwiera HTTPS. Query dokładnie `insecure` otwiera HTTP.
 * Stare DSN http i https parser czyta tak jak dawniej.
 * Klucz idzie wyłącznie w nagłówku X-Vulqen-Key, nigdy w URL.
 */
final class Dsn
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    private function __construct(
        public readonly string $endpoint,
        public readonly string $key,
        public readonly string $projectId,
        public readonly string $host,
    ) {
    }

    /**
     * @throws \InvalidArgumentException gdy DSN jest niepusty i nie da się go odczytać
     */
    public static function parse(?string $dsn): ?self
    {
        $dsn = trim((string) $dsn);
        if ($dsn === '') {
            return null;
        }

        $parts = parse_url($dsn);
        if ($parts === false || !isset($parts['scheme'], $parts['host'], $parts['user'], $parts['path'])) {
            throw new \InvalidArgumentException('DSN Vulqen musi mieć schemat, klucz, host i identyfikator projektu.');
        }

        $scheme = strtolower($parts['scheme']);
        if ($scheme === 'vulqen') {
            $transport = self::transportFromQuery($parts['query'] ?? null);
        } elseif ($scheme === 'http' || $scheme === 'https') {
            $transport = $scheme;
        } else {
            throw new \InvalidArgumentException('DSN Vulqen obsługuje schemat vulqen.');
        }

        $projectId = strtolower(trim($parts['path'], '/'));
        if (preg_match(self::UUID, $projectId) !== 1) {
            throw new \InvalidArgumentException('Ścieżka DSN Vulqen musi być UUID projektu.');
        }

        $key = rawurldecode($parts['user']);
        if ($key === '') {
            throw new \InvalidArgumentException('DSN Vulqen nie ma klucza.');
        }

        $host = strtolower($parts['host']);
        $authority = $host.(isset($parts['port']) ? ':'.$parts['port'] : '');

        return new self(
            sprintf('%s://%s/api/v1/projects/%s/envelope', $transport, $authority, $projectId),
            $key,
            $projectId,
            $host,
        );
    }

    private static function transportFromQuery(?string $query): string
    {
        if ($query === null) {
            return 'https';
        }

        if ($query === 'insecure') {
            return 'http';
        }

        throw new \InvalidArgumentException('DSN Vulqen przyjmuje tylko query insecure.');
    }
}
