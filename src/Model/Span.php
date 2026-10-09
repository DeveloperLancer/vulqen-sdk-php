<?php

declare(strict_types=1);

namespace Vulqen\Sdk\Model;

use Vulqen\Sdk\Ids;
use Vulqen\Sdk\Timestamp;

final class Span
{
    public const MAX_DESCRIPTION = 2048;

    public readonly string $spanId;

    private ?float $durationMs = null;

    /**
     * @param array<string, string|int|float|bool> $attrs
     */
    public function __construct(
        public readonly string $parentSpanId,
        public readonly string $op,
        public readonly string $description,
        public readonly float $startedAt,
        private array $attrs = [],
    ) {
        $this->spanId = Ids::spanId();
    }

    public function finish(?float $endedAt = null): void
    {
        if ($this->durationMs === null) {
            $this->durationMs = max(0.0, (($endedAt ?? microtime(true)) - $this->startedAt) * 1000);
        }
    }

    public function setAttr(string $name, string|int|float|bool $value): void
    {
        $this->attrs[$name] = $value;
    }

    public function isFinished(): bool
    {
        return $this->durationMs !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'span_id' => $this->spanId,
            'parent_span_id' => $this->parentSpanId,
            'op' => $this->op,
            'description' => mb_substr($this->description, 0, self::MAX_DESCRIPTION),
            'started_at' => Timestamp::format($this->startedAt),
            'duration_ms' => round($this->durationMs ?? 0.0, 3),
            'attrs' => $this->attrs === [] ? new \stdClass() : $this->attrs,
        ];
    }
}
