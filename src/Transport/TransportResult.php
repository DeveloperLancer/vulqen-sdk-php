<?php

declare(strict_types=1);

namespace Vulqen\Sdk\Transport;

final class TransportResult
{
    /**
     * @param array<string, mixed>|null $config blok config z odpowiedzi 202 (20)
     */
    public function __construct(
        public readonly int $status,
        public readonly ?int $retryAfter = null,
        public readonly ?array $config = null,
        public readonly ?string $error = null,
    ) {
    }

    public static function noResponse(string $error): self
    {
        return new self(0, null, null, $error);
    }

    public function accepted(): bool
    {
        return $this->status === 202;
    }

    public function rateLimited(): bool
    {
        return $this->status === 429;
    }

    /**
     * Zdrowie ingestu dla obwodu: odpowiedź 2xx-4xx oznacza, że serwer żyje, poza 429.
     */
    public function serverFailed(): bool
    {
        return $this->status === 0 || $this->status >= 500;
    }
}
