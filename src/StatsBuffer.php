<?php

declare(strict_types=1);

namespace Vulqen\Sdk;

use Vulqen\Sdk\Model\Transaction;

/**
 * Licznik każdej transakcji. Identyfikator zostaje ten sam po nieudanej wysyłce,
 * żeby ponowienie nie podwoiło słupka.
 */
final class StatsBuffer
{
    public const INTERVAL = 10.0;

    /** @var list<int> */
    private const UPPER_BOUNDS = [10, 25, 50, 100, 250, 500, 1000, 2500, 5000];

    /**
     * @var array<string, array{name: string, op: string, outcome: string, count: int, duration: float, buckets: list<int>}>
     */
    private array $series = [];

    private ?string $id = null;

    private ?float $periodStart = null;

    /** @var array{id: string, period_start: float, series: array<string, array{name: string, op: string, outcome: string, count: int, duration: float, buckets: list<int>}>}|null */
    private ?array $inflight = null;

    public function record(Transaction $transaction): void
    {
        $duration = $transaction->durationMs();
        $key = $transaction->name()."\0".$transaction->op."\0".$transaction->outcome();
        $row = $this->series[$key] ?? [
            'name' => $transaction->name(),
            'op' => $transaction->op,
            'outcome' => $transaction->outcome(),
            'count' => 0,
            'duration' => 0.0,
            'buckets' => array_fill(0, 10, 0),
        ];
        ++$row['count'];
        $row['duration'] += $duration;
        ++$row['buckets'][self::bucket($duration)];
        $this->series[$key] = $row;
        $this->id ??= Ids::uuid7();
        $this->periodStart ??= $transaction->startedAt;
    }

    public function due(float $now, bool $force): bool
    {
        if ($this->series === [] || $this->periodStart === null) {
            return false;
        }

        return $force || $now - $this->periodStart >= self::INTERVAL;
    }

    public function hasSeries(): bool
    {
        return $this->series !== [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pull(float $now): ?array
    {
        if ($this->series === [] || $this->id === null || $this->periodStart === null) {
            return null;
        }

        $this->inflight = [
            'id' => $this->id,
            'period_start' => $this->periodStart,
            'series' => $this->series,
        ];
        $item = self::item($this->inflight, $now);
        $this->series = [];
        $this->id = null;
        $this->periodStart = null;

        return $item;
    }

    public function restore(): void
    {
        if ($this->inflight === null) {
            return;
        }

        $restored = $this->inflight['series'];
        foreach ($this->series as $key => $row) {
            if (!isset($restored[$key])) {
                $restored[$key] = $row;
                continue;
            }
            $restored[$key]['count'] += $row['count'];
            $restored[$key]['duration'] += $row['duration'];
            foreach ($row['buckets'] as $index => $count) {
                $restored[$key]['buckets'][$index] += $count;
            }
        }
        $this->series = $restored;
        $this->id = $this->inflight['id'];
        $this->periodStart = $this->inflight['period_start'];
        $this->inflight = null;
    }

    public function commit(): void
    {
        $this->inflight = null;
    }

    /**
     * @param array{id: string, period_start: float, series: array<string, array{name: string, op: string, outcome: string, count: int, duration: float, buckets: list<int>}>} $held
     *
     * @return array<string, mixed>
     */
    private static function item(array $held, float $now): array
    {
        $transactions = [];
        foreach ($held['series'] as $row) {
            $transactions[] = [
                'name' => $row['name'],
                'op' => $row['op'],
                'outcome' => $row['outcome'],
                'count' => $row['count'],
                'duration_ms_sum' => round($row['duration'], 3),
                'buckets' => $row['buckets'],
            ];
        }

        return [
            'type' => 'stats',
            'id' => $held['id'],
            'period_start' => Timestamp::format($held['period_start']),
            'period_end' => Timestamp::format(max($now, $held['period_start'])),
            'transactions' => $transactions,
        ];
    }

    private static function bucket(float $durationMs): int
    {
        foreach (self::UPPER_BOUNDS as $index => $upper) {
            if ($durationMs <= $upper) {
                return $index;
            }
        }

        return 9;
    }
}
