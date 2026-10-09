# vulqen/php-sdk

Rdzeń zbierania zdarzeń, bez Symfony. Paczka Composer: `vulqen/php-sdk`.

Kanon: [vulqen-docs](https://github.com/DeveloperLancer/vulqen-docs), lokalnie `../vulqen-docs`. Opis: `plan/30-sdk-php.md`. Protokół: `plan/20-protokol-ingestu.md`. Zasady: [AGENTS.md](AGENTS.md).

## Użycie

Aplikacja Symfony używa bundla `vulqen/symfony-bundle`, który woła SDK sam. Bez frameworka:

```php
use Vulqen\Sdk\Client;
use Vulqen\Sdk\Options;

$client = new Client(new Options(
    dsn: getenv('VULQEN_DSN') ?: null,
    environment: 'prod',
    release: getenv('VULQEN_RELEASE') ?: null,
    projectRoot: __DIR__,
));

$transaction = $client->startTransaction('app_order_show', 'http.server');
$span = $client->startSpan('db.query', 'SELECT * FROM orders WHERE id = ?', ['db.system' => 'mysql']);
$client->finishSpan($span);
$transaction?->setHttpStatus(200);
$client->finishTransaction();
$client->flush();
```

Pusty DSN daje klienta, który nic nie robi. `flush()` woła się po wysłaniu odpowiedzi, na przykład po `fastcgi_finish_request()`.

## Testy

```bash
composer update
vendor/bin/phpunit
composer phpstan
```

Test kontraktu czyta `../vulqen/spec`, gdy katalog istnieje, potem `tests/fixtures/spec` (kopia tagu `VULQEN_SPEC_REF`), albo katalog z `VULQEN_SPEC_DIR`. Test e2e biegnie tylko z `VULQEN_E2E_DSN` (workflow `test-e2e.yml`).
