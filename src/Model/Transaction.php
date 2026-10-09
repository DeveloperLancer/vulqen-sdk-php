<?php

declare(strict_types=1);

namespace Vulqen\Sdk\Model;

use Vulqen\Sdk\Ids;
use Vulqen\Sdk\Timestamp;

/**
 * Jedna robota: żądanie HTTP, komenda albo komunikat (11, 20).
 */
final class Transaction
{
    public const OP_HTTP = 'http.server';
    public const OUTCOME_OK = 'ok';
    public const OUTCOME_ERROR = 'error';

    public readonly string $id;
    public readonly string $traceId;
    public readonly string $spanId;

    private string $outcome = self::OUTCOME_OK;
    private ?int $httpStatus = null;
    private ?int $memoryPeakBytes = null;
    private ?float $cpuMs = null;
    private ?float $durationMs = null;
    private bool $spansTruncated = false;

    /** @var list<Span> */
    private array $spans = [];

    public function __construct(
        private string $name,
        public readonly string $op,
        public readonly float $startedAt,
        private readonly int $maxSpans,
    ) {
        $this->id = Ids::uuid7($startedAt);
        $this->traceId = Ids::traceId();
        $this->spanId = Ids::spanId();
    }

    /**
     * Zwraca null ponad max_spans: transakcja dostaje spans_truncated, a wołający po prostu nie mierzy.
     *
     * @param array<string, string|int|float|bool> $attrs
     */
    public function startSpan(string $op, string $description, array $attrs = [], ?float $startedAt = null): ?Span
    {
        if ($this->durationMs !== null) {
            return null;
        }
        if (count($this->spans) >= $this->maxSpans) {
            $this->spansTruncated = true;

            return null;
        }

        $span = new Span($this->spanId, $op, $description, $startedAt ?? microtime(true), $attrs);
        $this->spans[] = $span;

        return $span;
    }

    public function setName(string $name): void
    {
        if ($name !== '') {
            $this->name = $name;
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function markError(): void
    {
        $this->outcome = self::OUTCOME_ERROR;
    }

    public function outcome(): string
    {
        return $this->outcome;
    }

    public function setHttpStatus(int $status): void
    {
        $this->httpStatus = $status;
    }

    public function setResources(?int $memoryPeakBytes, ?float $cpuMs): void
    {
        $this->memoryPeakBytes = $memoryPeakBytes === null ? null : max(0, $memoryPeakBytes);
        $this->cpuMs = $cpuMs === null ? null : max(0.0, $cpuMs);
    }

    public function finish(?float $endedAt = null): void
    {
        if ($this->durationMs !== null) {
            return;
        }
        $endedAt ??= microtime(true);
        $this->durationMs = max(0.0, ($endedAt - $this->startedAt) * 1000);
        foreach ($this->spans as $span) {
            $span->finish($endedAt);
        }
    }

    public function isFinished(): bool
    {
        return $this->durationMs !== null;
    }

    /**
     * @return list<Span>
     */
    public function spans(): array
    {
        return $this->spans;
    }

    public function dropSpans(): void
    {
        $this->spans = [];
        $this->spansTruncated = true;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $item = [
            'type' => 'transaction',
            'id' => $this->id,
            'trace_id' => $this->traceId,
            'span_id' => $this->spanId,
            'name' => $this->name,
            'op' => $this->op,
            'started_at' => Timestamp::format($this->startedAt),
            'duration_ms' => round($this->durationMs ?? 0.0, 3),
            'outcome' => $this->outcome,
        ];
        if ($this->op === self::OP_HTTP) {
            $item['http_status'] = $this->httpStatus !== null && $this->httpStatus >= 100 && $this->httpStatus <= 599 ? $this->httpStatus : 500;
        }
        if ($this->memoryPeakBytes !== null) {
            $item['memory_peak_bytes'] = $this->memoryPeakBytes;
        }
        if ($this->cpuMs !== null) {
            $item['cpu_ms'] = round($this->cpuMs, 3);
        }
        $item['spans_truncated'] = $this->spansTruncated;
        $item['spans'] = array_map(static fn (Span $span): array => $span->toArray(), $this->spans);

        return $item;
    }
}
