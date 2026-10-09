<?php

declare(strict_types=1);

namespace Vulqen\Sdk\Tests\Support;

use Vulqen\Sdk\Dsn;
use Vulqen\Sdk\Transport\Transport;
use Vulqen\Sdk\Transport\TransportResult;

final class RecordingTransport implements Transport
{
    /** @var list<string> */
    public array $sent = [];

    /** @var list<TransportResult|\Throwable> */
    private array $responses;

    public function __construct(TransportResult|\Throwable ...$responses)
    {
        $this->responses = array_values($responses);
    }

    public function send(Dsn $dsn, string $json): TransportResult
    {
        $this->sent[] = $json;
        $response = array_shift($this->responses) ?? new TransportResult(202);
        if ($response instanceof \Throwable) {
            throw $response;
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function envelope(int $index): array
    {
        $decoded = json_decode($this->sent[$index], true, 64, JSON_THROW_ON_ERROR);
        \assert(is_array($decoded));

        return $decoded;
    }
}
