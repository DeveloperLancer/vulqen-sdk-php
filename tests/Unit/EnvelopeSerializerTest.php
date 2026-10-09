<?php

declare(strict_types=1);

namespace Vulqen\Sdk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vulqen\Sdk\EnvelopeSerializer;
use Vulqen\Sdk\Model\ErrorEvent;
use Vulqen\Sdk\Model\ExceptionData;
use Vulqen\Sdk\Model\Transaction;
use Vulqen\Sdk\Options;
use Vulqen\Sdk\Tests\Support\Spec;

final class EnvelopeSerializerTest extends TestCase
{
    public function testEnvelopePrzechodziSchemaSpec(): void
    {
        $transaction = new Transaction('app_order_show', Transaction::OP_HTTP, 1_791_462_121.001, 1000);
        $span = $transaction->startSpan('db.query', 'SELECT * FROM orders WHERE id = ?', ['db.system' => 'mysql', 'db.rows' => 1], 1_791_462_121.02);
        $span?->finish(1_791_462_121.0234);
        $transaction->setHttpStatus(200);
        $transaction->setResources(25_165_824, 41.2);
        $transaction->finish(1_791_462_121.1834);

        $error = new ErrorEvent($transaction->traceId, $transaction->id, 1_791_462_121.18, ExceptionData::fromThrowable(new \RuntimeException('x', 0, new \LogicException('y')), null));

        $options = new Options(environment: 'preprod-eu', release: 'a1b2c3d', serverName: 'web-01');
        $envelopes = (new EnvelopeSerializer($options))->envelopes([
            EnvelopeSerializer::encodeItem($transaction->toArray()),
            EnvelopeSerializer::encodeItem($error->toArray()),
        ], 1_791_462_121.2);

        self::assertCount(1, $envelopes);
        $document = json_decode($envelopes[0]);
        self::assertInstanceOf(\stdClass::class, $document);
        $violations = Spec::violations($document);
        self::assertNull($violations, (string) $violations);
        self::assertSame('preprod-eu', $document->environment);
        self::assertCount(2, $document->items);
    }

    public function testPaczkiMajaNajwyzejStoElementow(): void
    {
        $items = [];
        for ($i = 0; $i < 250; ++$i) {
            $transaction = new Transaction('t'.$i, Transaction::OP_HTTP, 1_791_462_121.0, 10);
            $transaction->finish(1_791_462_121.1);
            $items[] = EnvelopeSerializer::encodeItem($transaction->toArray());
        }

        $envelopes = (new EnvelopeSerializer(new Options()))->envelopes($items, 1_791_462_122.0);

        self::assertSame([100, 100, 50], array_map(static fn (string $json): int => count(json_decode($json, true)['items']), $envelopes));
    }

    public function testPaczkaMiesciSiePodLimitemBajtow(): void
    {
        $big = '"'.str_repeat('a', 1_000_000).'"';
        $envelopes = (new EnvelopeSerializer(new Options()))->envelopes([$big, $big, $big, $big], 1_791_462_122.0);

        self::assertCount(2, $envelopes);
        foreach ($envelopes as $envelope) {
            self::assertLessThanOrEqual(EnvelopeSerializer::MAX_BYTES, strlen($envelope));
        }
    }

    public function testNazwaSrodowiskaJestDopasowanaDoWzorca(): void
    {
        self::assertSame('prod-eu-1', (new Options(environment: ' Prod EU.1 '))->environment);
        self::assertSame(Options::OTHER_ENVIRONMENT, (new Options(environment: '!!!'))->environment);
        self::assertNull((new Options(release: ' '))->release);
    }
}
