<?php

declare(strict_types=1);

namespace Vulqen\Sdk;

/**
 * Literały na ?, komentarze precz, jedna spacja (30, 17). Czysta funkcja.
 * Błędny SQL zwraca [unparsed] zamiast rzucać: lepiej zgubić zapytanie w rankingu niż żądanie.
 */
final class SqlNormalizer
{
    public const UNPARSED = '[unparsed]';
    public const MAX_LENGTH = 2048;

    public static function normalize(string $sql): string
    {
        $length = strlen($sql);
        $out = '';
        $i = 0;

        while ($i < $length) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($char === "'") {
                $end = self::stringEnd($sql, $i, $length);
                if ($end === null) {
                    return self::UNPARSED;
                }
                $out .= '?';
                $i = $end + 1;
                continue;
            }

            if ($char === '"' || $char === '`') {
                $end = strpos($sql, $char, $i + 1);
                if ($end === false) {
                    return self::UNPARSED;
                }
                $out .= substr($sql, $i, $end - $i + 1);
                $i = $end + 1;
                continue;
            }

            if ($char === '-' && $next === '-') {
                $end = strpos($sql, "\n", $i);
                $out .= ' ';
                $i = $end === false ? $length : $end + 1;
                continue;
            }

            if ($char === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                if ($end === false) {
                    return self::UNPARSED;
                }
                $out .= ' ';
                $i = $end + 2;
                continue;
            }

            if (ctype_digit($char) && !self::continuesIdentifier($out)) {
                $i = self::numberEnd($sql, $i, $length);
                $out .= '?';
                continue;
            }

            $out .= $char;
            ++$i;
        }

        $out = trim((string) preg_replace('/\s+/', ' ', $out));
        $out = (string) preg_replace('/(?<=[=<>(,]|\bIN|\bTHEN|\bELSE|\bAND|\bOR|\bSELECT)( ?)- ?\?/i', '$1?', $out);
        $out = (string) preg_replace('/\bIN ?\( ?\?(?: ?, ?\?)* ?\)/i', 'IN (?)', $out);
        $out = (string) preg_replace('/\b(VALUES ?\([^()]*\))(?: ?, ?\([^()]*\))+/i', '$1', $out);
        $out = Scrubber::maskDsn(trim((string) preg_replace('/ {2,}/', ' ', $out)));

        return mb_strlen($out) > self::MAX_LENGTH ? mb_substr($out, 0, self::MAX_LENGTH) : $out;
    }

    private static function stringEnd(string $sql, int $start, int $length): ?int
    {
        for ($i = $start + 1; $i < $length; ++$i) {
            if ($sql[$i] === '\\') {
                ++$i;
                continue;
            }
            if ($sql[$i] === "'") {
                if (($sql[$i + 1] ?? '') === "'") {
                    ++$i;
                    continue;
                }

                return $i;
            }
        }

        return null;
    }

    private static function numberEnd(string $sql, int $start, int $length): int
    {
        $i = $start;
        if ($sql[$i] === '0' && in_array($sql[$i + 1] ?? '', ['x', 'X', 'b', 'B'], true)) {
            $i += 2;
        }
        while ($i < $length && (ctype_alnum($sql[$i]) || $sql[$i] === '.' || $sql[$i] === '_')) {
            if (in_array($sql[$i], ['e', 'E'], true) && in_array($sql[$i + 1] ?? '', ['+', '-'], true)) {
                ++$i;
            }
            ++$i;
        }

        return $i;
    }

    /**
     * Cyfra po literze, _, $, : albo @ należy do identyfikatora albo parametru (t1, :p1, $1).
     */
    private static function continuesIdentifier(string $out): bool
    {
        if ($out === '') {
            return false;
        }
        $last = $out[strlen($out) - 1];

        return ctype_alnum($last) || in_array($last, ['_', '$', ':', '@'], true);
    }
}
