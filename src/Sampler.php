<?php

declare(strict_types=1);

namespace Vulqen\Sdk;

/**
 * Losowanie raz na proces (16). Wynik jedzie w fladze sampled nagłówka traceparent.
 */
final class Sampler
{
    public static function decide(float $rate): bool
    {
        if ($rate >= 1.0) {
            return true;
        }
        if ($rate <= 0.0) {
            return false;
        }

        return random_int(0, 1_000_000) / 1_000_000 < $rate;
    }
}
