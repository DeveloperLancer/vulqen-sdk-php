<?php

declare(strict_types=1);

namespace Vulqen\Sdk;

use Vulqen\Sdk\State\StateStore;

/**
 * Ostatni znany blok config z odpowiedzi 202 (16, 20). Brak bloku zostawia poprzednią wartość.
 * W Fazie 4 SDK stosuje z niego max_spans; resztę pól trzyma dla Fazy 7.
 */
final class RemoteConfig
{
    private const TTL = 86400;

    /** @var array<string, mixed>|null */
    private ?array $cached = null;

    private readonly string $key;

    public function __construct(private readonly StateStore $store, string $endpoint, private readonly int $defaultMaxSpans)
    {
        $this->key = 'config:'.$endpoint;
    }

    public function maxSpans(): int
    {
        $value = $this->values()['max_spans'] ?? null;

        return is_int($value) && $value > 0 ? $value : $this->defaultMaxSpans;
    }

    /**
     * @param array<string, mixed>|null $config
     */
    public function remember(?array $config): void
    {
        if ($config === null || $config === $this->values()) {
            return;
        }
        $this->cached = $config;
        $this->store->set($this->key, $config, self::TTL);
    }

    /**
     * @return array<string, mixed>
     */
    private function values(): array
    {
        return $this->cached ??= $this->store->get($this->key) ?? [];
    }
}
