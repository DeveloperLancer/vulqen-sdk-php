<?php

declare(strict_types=1);

namespace Vulqen\Sdk\Model;

/**
 * A metric or check-in waiting for the next flush.
 */
final class CapturedItem
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(private readonly array $payload)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->payload;
    }
}
