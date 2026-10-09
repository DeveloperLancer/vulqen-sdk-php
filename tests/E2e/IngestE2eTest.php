<?php

declare(strict_types=1);

namespace Vulqen\Sdk\Tests\E2e;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Vulqen\Sdk\Client;
use Vulqen\Sdk\Dsn;
use Vulqen\Sdk\Model\Transaction;
use Vulqen\Sdk\Options;
use Vulqen\Sdk\Transport\CurlTransport;
use Vulqen\Sdk\Transport\Transport;
use Vulqen\Sdk\Transport\TransportResult;

/**
 * Prawdziwy ingest (41). Uruchamia go test-e2e.yml z VULQEN_E2E_DSN. Workflow sprawdza potem wiersz w ClickHouse.
 */
#[Group('e2e')]
final class IngestE2eTest extends TestCase
{
    public function testSerwerPrzyjmujeEnvelopeZSdk(): void
    {
        $dsn = (string) getenv('VULQEN_E2E_DSN');
        if ($dsn === '') {
            self::markTestSkipped('Ustaw VULQEN_E2E_DSN, żeby uderzyć w prawdziwy ingest.');
        }

        $transport = new class implements Transport {
            /** @var list<TransportResult> */
            public array $results = [];

            public function send(Dsn $dsn, string $json): TransportResult
            {
                return $this->results[] = (new CurlTransport(1000, 5000))->send($dsn, $json);
            }
        };

        $client = new Client(new Options(dsn: $dsn, environment: 'e2e', transport: $transport, stateDir: sys_get_temp_dir().'/vulqen-e2e'));
        $transaction = $client->startTransaction('e2e_sdk', Transaction::OP_HTTP);
        $span = $client->startSpan('db.query', 'SELECT ?', ['db.system' => 'mysql']);
        $client->finishSpan($span);
        $client->captureException(new \RuntimeException('e2e'));
        $transaction?->setHttpStatus(200);
        $client->finishTransaction();
        $client->flush();

        self::assertCount(1, $transport->results);
        self::assertSame(202, $transport->results[0]->status, (string) $transport->results[0]->error);
        self::assertIsArray($transport->results[0]->config);
    }
}
