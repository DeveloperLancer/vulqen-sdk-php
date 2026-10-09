<?php

declare(strict_types=1);

namespace Vulqen\Sdk\Tests\Support;

use Vulqen\Sdk\State\StateStore;

final class MemoryStore implements StateStore
{
    /** @var array<string, array<string, mixed>> */
    public array $values = [];

    public function get(string $key): ?array
    {
        return $this->values[$key] ?? null;
    }

    public function set(string $key, array $value, int $ttlSeconds): void
    {
        $this->values[$key] = $value;
    }
}
