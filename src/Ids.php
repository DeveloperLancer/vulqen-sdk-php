<?php

declare(strict_types=1);

namespace Vulqen\Sdk;

final class Ids
{
    /**
     * UUID v7: 48 bitów milisekund, potem losowe bity z wersją i wariantem.
     */
    public static function uuid7(?float $at = null): string
    {
        $milliseconds = (int) round(($at ?? microtime(true)) * 1000);
        $time = str_pad(dechex($milliseconds), 12, '0', STR_PAD_LEFT);
        $random = random_bytes(10);
        $random[0] = chr((ord($random[0]) & 0x0F) | 0x70);
        $random[2] = chr((ord($random[2]) & 0x3F) | 0x80);
        $hex = $time.bin2hex($random);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }

    public static function traceId(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function spanId(): string
    {
        return bin2hex(random_bytes(8));
    }
}
