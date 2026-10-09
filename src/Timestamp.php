<?php

declare(strict_types=1);

namespace Vulqen\Sdk;

/**
 * RFC 3339 w UTC z milisekundami i Z, jedyny format czasu w envelope (20).
 */
final class Timestamp
{
    public static function format(float $unixSeconds): string
    {
        $milliseconds = (int) round($unixSeconds * 1000);
        $seconds = intdiv($milliseconds, 1000);

        return gmdate('Y-m-d\TH:i:s', $seconds).sprintf('.%03dZ', $milliseconds - $seconds * 1000);
    }
}
