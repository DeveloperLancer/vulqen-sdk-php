<?php

declare(strict_types=1);

namespace Vulqen\Sdk;

/**
 * Pierwsza linia prywatności (17, D-018). Serwer powtarza scrubbing, ale stare SDK zostaje u klientów.
 */
final class Scrubber
{
    public const MASK = '[scrubbed]';

    /** @var list<string> */
    public const HEADERS = ['authorization', 'cookie', 'set-cookie', 'proxy-authorization'];

    private const KEYS = 'password|passwd|pwd|secret|token|authorization|cookie|api_key|apikey';

    public static function scrub(string $text): string
    {
        $text = self::maskDsn($text);
        $text = (string) preg_replace('/\b(Bearer|Basic)\s+[A-Za-z0-9._~+\/=-]+/i', '$1 '.self::MASK, $text);

        return (string) preg_replace(
            '/(["\']?\b(?:[\w-]*(?:'.self::KEYS.')[\w-]*)\b["\']?\s*[:=]\s*)("[^"]*"|\'[^\']*\'|[^\s,;&)}\]]+)/i',
            '$1'.self::MASK,
            $text,
        );
    }

    /**
     * scheme://user:pass@host zamienia na scheme://***@host.
     */
    public static function maskDsn(string $text): string
    {
        return (string) preg_replace('~\b([a-z][a-z0-9+.-]*://)[^\s/:@\'"]+:[^\s/@\'"]+@~i', '$1***@', $text);
    }

    /**
     * @param array<string, mixed> $headers
     *
     * @return array<string, mixed>
     */
    public static function headers(array $headers): array
    {
        foreach (array_keys($headers) as $name) {
            if (in_array(strtolower($name), self::HEADERS, true)) {
                $headers[$name] = self::MASK;
            }
        }

        return $headers;
    }
}
