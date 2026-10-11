<?php

declare(strict_types=1);

namespace Vulqen\Sdk;

use Vulqen\Sdk\State\StateStore;

/**
 * Ostatni znany blok config z odpowiedzi 202 (16, 20). SDK stosuje go tylko, gdy version jest nowsza.
 * spans_stored jest sygnałem tej jednej odpowiedzi i nie zostaje w konfiguracji procesu.
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

        return is_int($value) && $value >= 1 && $value <= 1000 ? $value : $this->defaultMaxSpans;
    }

    public function sampleRate(): float
    {
        $value = $this->values()['sample_rate'] ?? 1;

        return is_int($value) || is_float($value) ? max(0.0, min(1.0, (float) $value)) : 1.0;
    }

    public function slowMs(): int
    {
        $value = $this->values()['slow_ms'] ?? 2000;

        return is_int($value) && $value >= 1 ? $value : 2000;
    }

    public function tracesEnabled(): bool
    {
        return $this->flag('traces_enabled', true);
    }

    public function sqlEnabled(): bool
    {
        return $this->flag('sql_enabled', true);
    }

    public function sqlBindings(): bool
    {
        return $this->flag('sql_bindings', false);
    }

    public function requestBody(): bool
    {
        return $this->flag('request_body', false);
    }

    /**
     * @param array<string, mixed>|null $config
     */
    public function remember(?array $config): void
    {
        if ($config === null) {
            return;
        }
        unset($config['spans_stored']);
        $version = $config['version'] ?? null;
        $current = $this->values()['version'] ?? 0;
        if (!is_int($version) || !is_int($current) || $version <= $current) {
            return;
        }
        $this->cached = $config;
        $this->store->set($this->key, $config, self::TTL);
    }

    private function flag(string $name, bool $default): bool
    {
        $value = $this->values()[$name] ?? null;

        return is_bool($value) ? $value : $default;
    }

    /**
     * @return array<string, mixed>
     */
    private function values(): array
    {
        return $this->cached ??= $this->store->get($this->key) ?? [];
    }
}
