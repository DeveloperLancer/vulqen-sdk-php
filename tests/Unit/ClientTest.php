<?php

declare(strict_types=1);

namespace Vulqen\Sdk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Vulqen\Sdk\Client;
use Vulqen\Sdk\Model\Transaction;
use Vulqen\Sdk\Options;
use Vulqen\Sdk\Tests\Support\MemoryStore;
use Vulqen\Sdk\Tests\Support\RecordingTransport;
use Vulqen\Sdk\Tests\Support\Spec;
use Vulqen\Sdk\Transport\TransportResult;

final class ClientTest extends TestCase
{
    private const DSN = 'http://vq_test_key@ingest.localhost:8100/01926b3a-7c2e-7a1b-8f3e-1c2d3e4f5a6b';

    public function testPustyDsnNicNieRobi(): void
    {
        $transport = new RecordingTransport();
        $client = new Client(new Options(dsn: '', transport: $transport));

        self::assertFalse($client->isEnabled());
        self::assertNull($client->startTransaction('t', Transaction::OP_HTTP));
        self::assertNull($client->startSpan('db.query', 'SELECT ?'));
        self::assertNull($client->captureException(new \RuntimeException('x')));
        $client->finishTransaction();
        $client->flush();

        self::assertSame([], $transport->sent);
    }

    public function testBlednyDsnWylaczaBezWyjatku(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $messages = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->messages[] = (string) $message;
            }
        };

        $client = new Client(new Options(dsn: 'nie dsn', logger: $logger));

        self::assertFalse($client->isEnabled());
        self::assertCount(1, $logger->messages);
    }

    public function testTransakcjaZeSpanemIWyjatkiemWJednymEnvelope(): void
    {
        $transport = new RecordingTransport();
        $client = $this->client($transport);

        $transaction = $client->startTransaction('app_order_show', Transaction::OP_HTTP);
        $span = $client->startSpan('db.query', 'SELECT * FROM orders WHERE id = ?', ['db.system' => 'mysql']);
        $client->finishSpan($span);
        $eventId = $client->captureException(new \RuntimeException('order 42 missing'));
        $transaction?->setHttpStatus(500);
        $client->finishTransaction();
        $client->flush();

        self::assertCount(1, $transport->sent);
        $document = json_decode($transport->sent[0]);
        self::assertInstanceOf(\stdClass::class, $document);
        self::assertNull(Spec::violations($document));

        $envelope = $transport->envelope(0);
        self::assertSame('vulqen-symfony', $envelope['sdk']['name']);
        [$error, $sent] = $envelope['items'];
        self::assertSame('error', $error['type']);
        self::assertSame($eventId, $error['id']);
        self::assertSame($sent['id'], $error['transaction_id']);
        self::assertSame($sent['trace_id'], $error['trace_id']);
        self::assertSame('error', $sent['outcome']);
        self::assertCount(1, $sent['spans']);
        self::assertNull($client->transaction());
    }

    public function testTrzyPorazkiOtwierajaObwodIPorzucajaBezSieci(): void
    {
        $transport = new RecordingTransport(
            TransportResult::noResponse('refused'),
            TransportResult::noResponse('refused'),
            new TransportResult(503),
        );
        $client = $this->client($transport);

        for ($i = 0; $i < 5; ++$i) {
            $client->startTransaction('t', Transaction::OP_HTTP);
            $client->finishTransaction();
            $client->flush();
        }

        self::assertCount(3, $transport->sent);
    }

    public function test429OtwieraObwodBezPonowienia(): void
    {
        $transport = new RecordingTransport(new TransportResult(429, 30));
        $client = $this->client($transport);

        for ($i = 0; $i < 3; ++$i) {
            $client->startTransaction('t', Transaction::OP_HTTP);
            $client->finishTransaction();
            $client->flush();
        }

        self::assertCount(1, $transport->sent);
    }

    public function testConfigZ202UstawiaMaxSpansNastepnejTransakcji(): void
    {
        $store = new MemoryStore();
        $transport = new RecordingTransport(new TransportResult(202, null, ['version' => 1, 'max_spans' => 2]));
        $client = new Client(new Options(dsn: self::DSN, transport: $transport, stateStore: $store));

        $client->startTransaction('pierwsza', Transaction::OP_HTTP);
        $client->finishTransaction();
        $client->flush();

        $second = new Client(new Options(dsn: self::DSN, transport: $transport, stateStore: $store));
        $transaction = $second->startTransaction('druga', Transaction::OP_HTTP);
        for ($i = 0; $i < 5; ++$i) {
            $second->startSpan('db.query', 'SELECT ?');
        }

        self::assertNotNull($transaction);
        self::assertCount(2, $transaction->spans());
    }

    public function testWyjatekTransportuNieWychodzi(): void
    {
        $client = $this->client(new RecordingTransport(new \Error('transport padł')));

        $client->startTransaction('t', Transaction::OP_HTTP);
        $client->finishTransaction();
        $client->flush();

        $this->addToAssertionCount(1);
    }

    public function testResetCzysciStanMiedzyZadaniami(): void
    {
        $transport = new RecordingTransport();
        $client = $this->client($transport);

        $client->startTransaction('pierwsze', Transaction::OP_HTTP);
        $client->startSpan('db.query', 'SELECT pierwsze');
        $client->captureException(new \RuntimeException('pierwsze'));
        $client->reset();

        $client->startTransaction('drugie', Transaction::OP_HTTP);
        $client->finishTransaction();
        $client->flush();

        self::assertCount(1, $transport->sent);
        $items = $transport->envelope(0)['items'];
        self::assertCount(1, $items);
        self::assertSame('drugie', $items[0]['name']);
        self::assertSame([], $items[0]['spans']);
    }

    public function testPorzuconaTransakcjaNieJestWysylana(): void
    {
        $transport = new RecordingTransport();
        $client = $this->client($transport);

        $client->startTransaction('app_health', Transaction::OP_HTTP);
        $client->discardTransaction();
        $client->finishTransaction();
        $client->flush();

        self::assertSame([], $transport->sent);
    }

    private function client(RecordingTransport $transport): Client
    {
        return new Client(new Options(
            dsn: self::DSN,
            sdkName: 'vulqen-symfony',
            transport: $transport,
            stateStore: new MemoryStore(),
        ));
    }
}
