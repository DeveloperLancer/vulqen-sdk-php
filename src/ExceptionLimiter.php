<?php

declare(strict_types=1);

namespace Vulqen\Sdk;

/**
 * Najwyżej 20 pełnych wyjątków na minutę na odcisk w jednym procesie (R-003).
 * Stan żyje dłużej niż transakcja: reset po żądaniu nie może otworzyć pętli błędów na nowo.
 */
final class ExceptionLimiter
{
    public const LIMIT = 20;
    public const WINDOW_SECONDS = 60;
    private const MAX_FINGERPRINTS = 1000;

    /** @var array<string, array{0: float, 1: int}> */
    private array $windows = [];

    public function allow(\Throwable $throwable, ?float $now = null): bool
    {
        $now ??= microtime(true);
        $fingerprint = $throwable::class.'|'.$throwable->getFile().'|'.$throwable->getLine();
        [$start, $count] = $this->windows[$fingerprint] ?? [$now, 0];

        if ($now - $start >= self::WINDOW_SECONDS) {
            [$start, $count] = [$now, 0];
        }
        if ($count >= self::LIMIT) {
            return false;
        }
        if (!isset($this->windows[$fingerprint]) && count($this->windows) >= self::MAX_FINGERPRINTS) {
            array_shift($this->windows);
        }
        $this->windows[$fingerprint] = [$start, $count + 1];

        return true;
    }
}
