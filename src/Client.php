<?php

declare(strict_types=1);

namespace Vulqen\Sdk;

use Vulqen\Sdk\Model\ErrorEvent;
use Vulqen\Sdk\Model\ExceptionData;
use Vulqen\Sdk\Model\Span;
use Vulqen\Sdk\Model\Transaction;
use Vulqen\Sdk\State\ApcuStore;
use Vulqen\Sdk\State\FileStore;
use Vulqen\Sdk\Transport\CurlTransport;
use Vulqen\Sdk\Transport\Transport;

/**
 * Jedyne wejście, którego używa bundle (30). Każda metoda publiczna łapie \Throwable
 * i wraca tak, jakby monitoringu nie było (D-020). Pusty DSN daje klienta, który nic nie robi.
 */
final class Client
{
    private ?Dsn $dsn = null;
    private ?Transport $transport = null;
    private ?CircuitBreaker $breaker = null;
    private ?RemoteConfig $config = null;
    private ?EnvelopeSerializer $serializer = null;
    private ExceptionLimiter $limiter;

    private ?Transaction $transaction = null;

    /** @var list<Transaction> */
    private array $paused = [];

    /** @var list<Transaction|ErrorEvent> */
    private array $pending = [];

    private StatsBuffer $stats;

    private bool $processSampled = true;

    public function __construct(private readonly Options $options)
    {
        $this->limiter = new ExceptionLimiter();
        $this->stats = new StatsBuffer();
        try {
            $this->dsn = Dsn::parse($options->dsn);
            if ($this->dsn === null) {
                return;
            }
            $store = $options->stateStore ?? (ApcuStore::available() ? new ApcuStore() : new FileStore($options->stateDir));
            $this->transport = $options->transport ?? new CurlTransport();
            $this->breaker = new CircuitBreaker($store, $this->dsn->endpoint);
            $this->config = new RemoteConfig($store, $this->dsn->endpoint, $options->maxSpans);
            $this->serializer = new EnvelopeSerializer($options);
            $this->processSampled = Sampler::decide($this->config->sampleRate());
            register_shutdown_function(function (): void {
                $this->flush(true);
            });
        } catch (\Throwable $e) {
            $this->dsn = null;
            $this->debug('Vulqen wyłączony: {error}', $e);
        }
    }

    public function isEnabled(): bool
    {
        return $this->dsn !== null;
    }

    /**
     * Host ingestu z DSN. Bundle nie instrumentuje wywołań na ten host (R-011).
     */
    public function ingestHost(): ?string
    {
        return $this->dsn?->host;
    }

    public function startTransaction(string $name, string $op, ?float $startedAt = null, ?string $traceparent = null): ?Transaction
    {
        if ($this->dsn === null) {
            return null;
        }
        try {
            $parent = Traceparent::parse($traceparent);
            $parentUnsampled = $parent !== null && !$parent->sampled;
            $created = new Transaction(
                $name,
                $op,
                $startedAt ?? microtime(true),
                $this->config?->maxSpans() ?? $this->options->maxSpans,
                $parent === null ? $this->processSampled : $parent->sampled,
                $parent?->spanId,
                $parentUnsampled,
                $parent?->traceId,
            );
            if ($this->transaction !== null) {
                $this->paused[] = $this->transaction;
            }

            return $this->transaction = $created;
        } catch (\Throwable $e) {
            $this->debug('Vulqen startTransaction: {error}', $e);

            return null;
        }
    }

    /**
     * traceparent bieżącej transakcji. Flaga sampled jest decyzją ze startu.
     */
    public function traceparent(): ?string
    {
        return $this->transaction?->traceparent();
    }

    public function transaction(): ?Transaction
    {
        return $this->transaction;
    }

    /**
     * @param array<string, string|int|float|bool> $attrs
     */
    public function startSpan(string $op, string $description, array $attrs = []): ?Span
    {
        if ($this->transaction === null) {
            return null;
        }
        try {
            if ($op === 'db.query' && $this->config?->sqlEnabled() === false) {
                return null;
            }
            if (!$this->config?->sqlBindings()) {
                unset($attrs['db.bindings']);
            } elseif (isset($attrs['db.bindings'])) {
                $bindings = $attrs['db.bindings'];
                $text = is_string($bindings) ? $bindings : (string) json_encode($bindings, JSON_THROW_ON_ERROR);
                $attrs['db.bindings'] = Scrubber::scrub($text);
            }

            return $this->transaction->startSpan($op, $description, $attrs);
        } catch (\Throwable $e) {
            $this->debug('Vulqen startSpan: {error}', $e);

            return null;
        }
    }

    public function finishSpan(?Span $span): void
    {
        try {
            $span?->finish();
        } catch (\Throwable $e) {
            $this->debug('Vulqen finishSpan: {error}', $e);
        }
    }

    /**
     * Zwraca identyfikator zdarzenia albo null, gdy SDK jest wyłączone albo odcisk przekroczył limit.
     */
    public function captureException(\Throwable $throwable): ?string
    {
        if ($this->dsn === null) {
            return null;
        }
        try {
            $this->transaction?->markError();
            if (!$this->limiter->allow($throwable)) {
                return null;
            }
            $event = new ErrorEvent(
                $this->transaction->traceId ?? Ids::traceId(),
                $this->transaction?->id,
                microtime(true),
                ExceptionData::fromThrowable($throwable, $this->options->projectRoot),
            );
            $this->pending[] = $event;

            return $event->id;
        } catch (\Throwable $e) {
            $this->debug('Vulqen captureException: {error}', $e);

            return null;
        }
    }

    public function setRequestBody(?string $body): void
    {
        if ($this->transaction === null || $this->config?->requestBody() !== true || $body === null || $body === '') {
            return;
        }
        try {
            $this->transaction->setRequestBody(substr(Scrubber::scrub(substr($body, 0, 16384)), 0, 8192));
        } catch (\Throwable $e) {
            $this->debug('Vulqen setRequestBody: {error}', $e);
        }
    }

    public function finishTransaction(): void
    {
        try {
            if ($this->transaction !== null) {
                $fresh = !$this->transaction->isFinished();
                $this->transaction->finish();
                if ($fresh) {
                    $this->stats->record($this->transaction);
                    if ($this->transaction->shouldSend($this->config?->tracesEnabled() ?? true, $this->config?->slowMs() ?? 2000)) {
                        $this->pending[] = $this->transaction;
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->debug('Vulqen finishTransaction: {error}', $e);
        } finally {
            $this->transaction = array_pop($this->paused) ?: null;
        }
    }

    public function discardTransaction(): void
    {
        $this->transaction = array_pop($this->paused) ?: null;
    }

    /**
     * Wysyła pełne ślady. Liczniki schodzą razem z nimi, co 10 sekund albo przy końcu procesu.
     * Otwarty obwód i 429 porzucają ślady, a liczniki zostają do następnej udanej odpowiedzi.
     */
    public function flush(bool $forceStats = false): void
    {
        $pending = $this->pending;
        $this->pending = [];
        if ($this->dsn === null || $this->transport === null || $this->breaker === null || $this->serializer === null) {
            return;
        }

        try {
            $now = microtime(true);
            if ($this->breaker->isOpen($now)) {
                return;
            }
            $attachStats = $pending !== [] || $this->stats->due($now, $forceStats);
            $stats = $attachStats ? $this->stats->pull($now) : null;
            if ($pending === [] && $stats === null) {
                return;
            }

            $encoded = $this->encode($pending);
            if ($stats !== null) {
                $encoded[] = EnvelopeSerializer::encodeItem($stats);
            }
            if ($encoded === []) {
                $this->stats->restore();

                return;
            }

            foreach ($this->serializer->envelopes($encoded, $now) as $envelope) {
                $result = $this->transport->send($this->dsn, $envelope);
                if ($result->rateLimited()) {
                    $this->stats->restore();
                    $this->breaker->openFor($result->retryAfter);

                    return;
                }
                if ($result->serverFailed()) {
                    $this->stats->restore();
                    $this->breaker->recordFailure();
                    $this->debug('Vulqen ingest nie odpowiedział: {error}', null, ['error' => $result->error ?? 'HTTP '.$result->status]);

                    return;
                }
                $this->breaker->recordSuccess();
                if (!$result->accepted()) {
                    $this->stats->restore();
                    $this->debug('Vulqen ingest odrzucił envelope: HTTP {status}', null, ['status' => $result->status]);

                    return;
                }
                $this->config?->remember($result->config);
            }
            $this->stats->commit();
        } catch (\Throwable $e) {
            $this->stats->restore();
            $this->debug('Vulqen flush: {error}', $e);
        }
    }

    /**
     * Czyści transakcję i ślady oczekujące. Liczniki procesu zostają (worker żyje dłużej niż jedna wiadomość).
     */
    public function reset(): void
    {
        $this->transaction = null;
        $this->paused = [];
        $this->pending = [];
    }

    /**
     * @param list<Transaction|ErrorEvent> $items
     *
     * @return list<string>
     *
     * @throws \JsonException
     */
    private function encode(array $items): array
    {
        $encoded = [];
        foreach ($items as $item) {
            $json = EnvelopeSerializer::encodeItem($item->toArray());
            if (strlen($json) > EnvelopeSerializer::MAX_BYTES && $item instanceof Transaction) {
                $item->dropSpans();
                $json = EnvelopeSerializer::encodeItem($item->toArray());
            }
            if (strlen($json) <= EnvelopeSerializer::MAX_BYTES) {
                $encoded[] = $json;
            }
        }

        return $encoded;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function debug(string $message, ?\Throwable $error, array $context = []): void
    {
        if ($this->options->logger === null) {
            return;
        }
        try {
            if ($error !== null) {
                $context['error'] = $error->getMessage();
            }
            $this->options->logger->debug($message, $context);
        } catch (\Throwable) {
        }
    }
}
