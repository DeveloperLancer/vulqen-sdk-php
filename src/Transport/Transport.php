<?php

declare(strict_types=1);

namespace Vulqen\Sdk\Transport;

use Vulqen\Sdk\Dsn;

interface Transport
{
    /**
     * Wysyła jeden envelope JSON. Nie rzuca: brak odpowiedzi to status 0.
     */
    public function send(Dsn $dsn, string $json): TransportResult;
}
