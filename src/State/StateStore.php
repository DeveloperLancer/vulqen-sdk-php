<?php

declare(strict_types=1);

namespace Vulqen\Sdk\State;

/**
 * Stan współdzielony między żądaniami jednego hosta: obwód i ostatni config z 202.
 * Błąd zapisu albo odczytu jest cichy, bo stan jest optymalizacją, nie warunkiem wysyłki.
 */
interface StateStore
{
    /**
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array;

    /**
     * @param array<string, mixed> $value
     */
    public function set(string $key, array $value, int $ttlSeconds): void;
}
