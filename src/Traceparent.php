<?php

declare(strict_types=1);

namespace Vulqen\Sdk;

/**
 * Nagłówek W3C traceparent: 00-{traceId}-{spanId}-{flag}.
 * Flaga 00 oznacza, że rodzic nie wylosował pełnego śladu.
 */
final readonly class Traceparent
{
    public function __construct(
        public string $traceId,
        public string $spanId,
        public bool $sampled,
    ) {
    }

    public static function parse(?string $header): ?self
    {
        if (!is_string($header)) {
            return null;
        }
        if (preg_match('/^00-([0-9a-f]{32})-([0-9a-f]{16})-([0-9a-f]{2})$/', strtolower(trim($header)), $matches) !== 1) {
            return null;
        }

        return new self($matches[1], $matches[2], $matches[3] === '01');
    }

    public function header(): string
    {
        return sprintf('00-%s-%s-%s', $this->traceId, $this->spanId, $this->sampled ? '01' : '00');
    }
}
