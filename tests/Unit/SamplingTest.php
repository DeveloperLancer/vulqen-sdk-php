<?php

declare(strict_types=1);

namespace Vulqen\Sdk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vulqen\Sdk\Client;
use Vulqen\Sdk\Dsn;
use Vulqen\Sdk\Model\Transaction;
use Vulqen\Sdk\Options;
use Vulqen\Sdk\Tests\Support\MemoryStore;
use Vulqen\Sdk\Tests\Support\RecordingTransport;
use Vulqen\Sdk\Tests\Support\Spec;
use Vulqen\Sdk\Transport\TransportResult;

final class SamplingTest extends TestCase
{
    private const DSN = 'vulqen://vq_test_key@ingest.localhost:8100/01926b3a-7c2e-7a1b-8f3e-1c2d3e4f5a6b?insecure';

    public function testAZeroSampleRateSendsCountersWithoutTheTrace(): void
    {
        $transport = new RecordingTransport();
        $client = $this->client($transport, ['sample_rate' => 0]);

        $client->startTransaction('app_order_show', Transaction::OP_HTTP);
        $client->finishTransaction();
        $client->flush(true);

        $items = $transport->envelope(0)['items'];
        self::assertSame(['stats'], array_column($items, 'type'));
        self::assertSame(1, $items[0]['transactions'][0]['count']);
        self::assertNull(Spec::violations(json_decode($transport->sent[0])));
    }

    public function testAnErrorAndASlowRequestStayOnTheTraceDespiteSampling(): void
    {
        $transport = new RecordingTransport();
        $client = $this->client($transport, ['sample_rate' => 0, 'slow_ms' => 1000]);

        $client->startTransaction('app_fail', Transaction::OP_HTTP);
        $client->captureException(new \RuntimeException('boom'));
        $client->finishTransaction();

        $slow = $this->client($transport, ['sample_rate' => 0, 'slow_ms' => 1000]);
        $slow->startTransaction('app_slow', Transaction::OP_HTTP, microtime(true) - 2);
        $slow->finishTransaction();
        $client->flush();
        $slow->flush();

        $names = [];
        foreach ($transport->sent as $body) {
            foreach (json_decode($body, true)['items'] as $item) {
                if ($item['type'] === 'transaction') {
                    $names[] = $item['name'];
                }
            }
        }
        self::assertSame(['app_fail', 'app_slow'], $names);
    }

    public function testAChildWithAnUnsampledParentSendsOnlyOnErrorAndKeepsTheParentSpan(): void
    {
        $transport = new RecordingTransport();
        $client = $this->client($transport, ['sample_rate' => 1]);
        $trace = bin2hex(random_bytes(16));
        $parent = bin2hex(random_bytes(8));

        $quiet = $client->startTransaction('App\\Quiet', 'queue.process', null, sprintf('00-%s-%s-00', $trace, $parent));
        self::assertNotNull($quiet);
        self::assertStringEndsWith('-00', $quiet->traceparent());
        $client->finishTransaction();
        $client->flush(true);
        self::assertSame(['stats'], array_column($transport->envelope(0)['items'], 'type'));

        $failing = $client->startTransaction('App\\Fail', 'queue.process', null, sprintf('00-%s-%s-00', $trace, $parent));
        $client->captureException(new \RuntimeException('worker'));
        $client->finishTransaction();
        $client->flush();

        $sent = $this->transaction($transport->envelope(1));
        self::assertSame('App\\Fail', $sent['name']);
        self::assertSame($trace, $sent['trace_id']);
        self::assertSame($parent, $sent['parent_span_id']);
        self::assertStringEndsWith('-00', $failing?->traceparent() ?? '');
    }

    public function testDisabledTracesAndSqlFollowTheRemoteConfig(): void
    {
        $transport = new RecordingTransport();
        $client = $this->client($transport, [
            'traces_enabled' => false,
            'sql_enabled' => false,
            'sql_bindings' => true,
            'request_body' => true,
        ]);

        $transaction = $client->startTransaction('app_order_show', Transaction::OP_HTTP);
        self::assertNull($client->startSpan('db.query', 'SELECT ?', ['db.bindings' => 'password=hunter2']));
        self::assertNotNull($client->startSpan('http.client', 'GET https://example.test'));
        $client->setRequestBody('token=sekret');
        $client->finishTransaction();
        $client->flush(true);

        self::assertSame(['stats'], array_column($transport->envelope(0)['items'], 'type'));
        self::assertNotNull($transaction);
        self::assertSame([], array_values(array_filter(
            $transaction->spans(),
            static fn ($span): bool => $span->op === 'db.query',
        )));
    }

    public function testBindingsAndBodyAreScrubbedWhenTheProjectTurnsThemOn(): void
    {
        $transport = new RecordingTransport();
        $client = $this->client($transport, ['sql_bindings' => true, 'request_body' => true]);

        $client->startTransaction('app_order_show', Transaction::OP_HTTP);
        $span = $client->startSpan('db.query', 'SELECT ?', ['db.system' => 'mysql', 'db.bindings' => 'password=hunter2']);
        $client->setRequestBody('note ada@example.com');
        $client->finishSpan($span);
        $client->finishTransaction();
        $client->flush();

        $transaction = $this->transaction($transport->envelope(0));
        self::assertSame('password=[scrubbed]', $transaction['spans'][0]['attrs']['db.bindings']);
        self::assertSame('note ada@example.com', $transaction['http.request.body']);
        self::assertNull(Spec::violations(json_decode($transport->sent[0])));
    }

    public function testARateLimitDropsTheTraceAndSendsTheCounterAfterTheCircuitCloses(): void
    {
        $store = new MemoryStore();
        $transport = new RecordingTransport(new TransportResult(429, 30), new TransportResult(202));
        $client = new Client(new Options(dsn: self::DSN, transport: $transport, stateStore: $store));
        $endpoint = Dsn::parse(self::DSN)?->endpoint;
        self::assertIsString($endpoint);

        $client->startTransaction('app_order_show', Transaction::OP_HTTP);
        $client->finishTransaction();
        $client->flush();
        self::assertCount(1, $transport->sent);
        self::assertSame(['transaction', 'stats'], array_column(json_decode($transport->sent[0], true)['items'], 'type'));

        $store->values['circuit:'.$endpoint] = ['failures' => 0, 'open_until' => 0.0];
        $client->flush(true);

        self::assertCount(2, $transport->sent);
        $second = json_decode($transport->sent[1], true);
        self::assertSame(['stats'], array_column($second['items'], 'type'));
        self::assertSame(
            json_decode($transport->sent[0], true)['items'][1]['id'],
            $second['items'][0]['id'],
        );
    }

    public function testAnOlderConfigAndSpansStoredAreNotKept(): void
    {
        $store = new MemoryStore();
        $transport = new RecordingTransport(
            new TransportResult(202, null, ['version' => 2, 'max_spans' => 3, 'spans_stored' => false]),
            new TransportResult(202, null, ['version' => 1, 'max_spans' => 1, 'spans_stored' => false]),
        );
        $client = new Client(new Options(dsn: self::DSN, transport: $transport, stateStore: $store));
        $client->startTransaction('pierwsza', Transaction::OP_HTTP);
        $client->finishTransaction();
        $client->flush();
        $client->startTransaction('druga', Transaction::OP_HTTP);
        $client->finishTransaction();
        $client->flush();

        $endpoint = Dsn::parse(self::DSN)?->endpoint;
        self::assertIsString($endpoint);
        $stored = $store->values['config:'.$endpoint];
        self::assertSame(2, $stored['version']);
        self::assertSame(3, $stored['max_spans']);
        self::assertArrayNotHasKey('spans_stored', $stored);
    }

    public function testResetKeepsProcessCounters(): void
    {
        $transport = new RecordingTransport();
        $client = $this->client($transport, ['sample_rate' => 0]);

        $client->startTransaction('pierwsze', Transaction::OP_HTTP);
        $client->finishTransaction();
        $client->reset();
        $client->flush(true);

        self::assertSame(1, $transport->envelope(0)['items'][0]['transactions'][0]['count']);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function client(RecordingTransport $transport, array $config): Client
    {
        $store = new MemoryStore();
        $endpoint = Dsn::parse(self::DSN)?->endpoint;
        self::assertIsString($endpoint);
        $store->set('config:'.$endpoint, ['version' => 1] + $config, 86400);

        return new Client(new Options(dsn: self::DSN, transport: $transport, stateStore: $store));
    }

    /**
     * @param array<string, mixed> $envelope
     *
     * @return array<string, mixed>
     */
    private function transaction(array $envelope): array
    {
        foreach ($envelope['items'] as $item) {
            if (is_array($item) && ($item['type'] ?? null) === 'transaction') {
                return $item;
            }
        }

        self::fail('Brak transakcji w envelope.');
    }
}
