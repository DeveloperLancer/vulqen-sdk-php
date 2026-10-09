<?php

declare(strict_types=1);

namespace Vulqen\Sdk;

use Vulqen\Sdk\State\StateStore;

/**
 * Trzy porażki z rzędu otwierają obwód na 30 s, 429 na Retry-After, najwyżej 60 s (30, D-020).
 * Otwarty obwód oznacza porzucenie envelope bez dotykania sieci.
 */
final class CircuitBreaker
{
    public const FAILURE_THRESHOLD = 3;
    public const OPEN_SECONDS = 30;
    public const MAX_RETRY_AFTER = 60;

    private const TTL = 3600;

    private readonly string $key;

    public function __construct(private readonly StateStore $store, string $endpoint)
    {
        $this->key = 'circuit:'.$endpoint;
    }

    public function isOpen(?float $now = null): bool
    {
        $openUntil = $this->state()['open_until'];

        return $openUntil > ($now ?? microtime(true));
    }

    public function recordSuccess(): void
    {
        $state = $this->state();
        if ($state['failures'] !== 0 || $state['open_until'] !== 0.0) {
            $this->store->set($this->key, ['failures' => 0, 'open_until' => 0.0], self::TTL);
        }
    }

    public function recordFailure(?float $now = null): void
    {
        $now ??= microtime(true);
        $failures = $this->state()['failures'] + 1;
        if ($failures >= self::FAILURE_THRESHOLD) {
            $this->store->set($this->key, ['failures' => 0, 'open_until' => $now + self::OPEN_SECONDS], self::TTL);

            return;
        }
        $this->store->set($this->key, ['failures' => $failures, 'open_until' => 0.0], self::TTL);
    }

    public function openFor(?int $retryAfter, ?float $now = null): void
    {
        $seconds = min(self::MAX_RETRY_AFTER, max(1, $retryAfter ?? self::MAX_RETRY_AFTER));
        $this->store->set($this->key, ['failures' => 0, 'open_until' => ($now ?? microtime(true)) + $seconds], self::TTL);
    }

    /**
     * @return array{failures: int, open_until: float}
     */
    private function state(): array
    {
        $state = $this->store->get($this->key) ?? [];

        return [
            'failures' => is_int($state['failures'] ?? null) ? $state['failures'] : 0,
            'open_until' => is_float($state['open_until'] ?? null) || is_int($state['open_until'] ?? null) ? (float) $state['open_until'] : 0.0,
        ];
    }
}
