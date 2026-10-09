<?php

declare(strict_types=1);

namespace Vulqen\Sdk\State;

final class ApcuStore implements StateStore
{
    public function __construct(private readonly string $prefix = 'vulqen:')
    {
    }

    public static function available(): bool
    {
        return function_exists('apcu_enabled') && apcu_enabled();
    }

    public function get(string $key): ?array
    {
        $value = apcu_fetch($this->prefix.$key, $success);

        return $success && is_array($value) ? $value : null;
    }

    public function set(string $key, array $value, int $ttlSeconds): void
    {
        apcu_store($this->prefix.$key, $value, max(1, $ttlSeconds));
    }
}
