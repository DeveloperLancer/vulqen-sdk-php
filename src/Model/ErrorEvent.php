<?php

declare(strict_types=1);

namespace Vulqen\Sdk\Model;

use Vulqen\Sdk\Ids;
use Vulqen\Sdk\Timestamp;

final class ErrorEvent
{
    public readonly string $id;

    public function __construct(
        public readonly string $traceId,
        public readonly ?string $transactionId,
        public readonly float $occurredAt,
        public readonly ExceptionData $exception,
    ) {
        $this->id = Ids::uuid7($occurredAt);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $item = ['type' => 'error', 'id' => $this->id, 'trace_id' => $this->traceId];
        if ($this->transactionId !== null) {
            $item['transaction_id'] = $this->transactionId;
        }
        $item['occurred_at'] = Timestamp::format($this->occurredAt);
        $item['exception'] = $this->exception->toArray();

        return $item;
    }
}
