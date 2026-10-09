<?php

declare(strict_types=1);

namespace Vulqen\Sdk\State;

/**
 * Jeden plik JSON na klucz w katalogu tymczasowym. Używany, gdy host nie ma APCu (D-020).
 */
final class FileStore implements StateStore
{
    private readonly string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = rtrim($directory ?? sys_get_temp_dir(), '/\\');
    }

    public function get(string $key): ?array
    {
        $raw = @file_get_contents($this->path($key));
        if ($raw === false || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['expires_at'], $decoded['value']) || !is_array($decoded['value'])) {
            return null;
        }

        return $decoded['expires_at'] >= microtime(true) ? $decoded['value'] : null;
    }

    public function set(string $key, array $value, int $ttlSeconds): void
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }
        $json = json_encode(['expires_at' => microtime(true) + max(1, $ttlSeconds), 'value' => $value]);
        if ($json !== false) {
            @file_put_contents($this->path($key), $json, LOCK_EX);
        }
    }

    private function path(string $key): string
    {
        return $this->directory.DIRECTORY_SEPARATOR.'vulqen-'.sha1($key).'.json';
    }
}
