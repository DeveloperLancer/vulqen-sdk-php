<?php

declare(strict_types=1);

namespace Vulqen\Sdk\Transport;

use Vulqen\Sdk\Dsn;
use Vulqen\Sdk\Version;

/**
 * Curl z twardymi limitami czasu (30, D-020). Ponowienie tylko wtedy, gdy nie przyszedł żaden status HTTP.
 */
final class CurlTransport implements Transport
{
    public const CONNECT_TIMEOUT_MS = 200;
    public const TIMEOUT_MS = 1000;

    public function __construct(
        private readonly int $connectTimeoutMs = self::CONNECT_TIMEOUT_MS,
        private readonly int $timeoutMs = self::TIMEOUT_MS,
    ) {
    }

    public function send(Dsn $dsn, string $json): TransportResult
    {
        $body = gzencode($json, 6);
        if ($body === false) {
            return TransportResult::noResponse('gzencode');
        }

        $result = $this->attempt($dsn, $body);

        return $result->status === 0 ? $this->attempt($dsn, $body) : $result;
    }

    private function attempt(Dsn $dsn, string $body): TransportResult
    {
        $handle = curl_init($dsn->endpoint);
        if ($handle === false) {
            return TransportResult::noResponse('curl_init');
        }

        $retryAfter = null;
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_CONNECTTIMEOUT_MS => $this->connectTimeoutMs,
            CURLOPT_TIMEOUT_MS => $this->timeoutMs,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Encoding: gzip',
                'X-Vulqen-Key: '.$dsn->key,
                'User-Agent: vulqen-php/'.Version::NUMBER,
                'Expect:',
            ],
            CURLOPT_HEADERFUNCTION => static function (\CurlHandle $curl, string $header) use (&$retryAfter): int {
                if (stripos($header, 'retry-after:') === 0) {
                    $value = trim(substr($header, 12));
                    $retryAfter = ctype_digit($value) ? (int) $value : null;
                }

                return strlen($header);
            },
        ]);

        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        unset($handle);

        if ($response === false || $status === 0) {
            return TransportResult::noResponse($error !== '' ? $error : 'brak odpowiedzi');
        }

        return new TransportResult($status, $retryAfter, $status === 202 ? self::config((string) $response) : null);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function config(string $response): ?array
    {
        try {
            $decoded = json_decode($response, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) && isset($decoded['config']) && is_array($decoded['config']) ? $decoded['config'] : null;
    }
}
