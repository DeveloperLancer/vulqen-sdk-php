<?php

declare(strict_types=1);

// Router dla php -S w CurlTransportTest. Końcówka UUID projektu wybiera odpowiedź.

$path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
if (preg_match('#^/api/v1/projects/[0-9a-f-]{32}(\d{4})/envelope$#', $path, $match) !== 1) {
    http_response_code(404);

    return;
}

$log = sys_get_temp_dir().'/vulqen-router-'.$_SERVER['SERVER_PORT'].'.jsonl';
$raw = (string) file_get_contents('php://input');
file_put_contents($log, json_encode([
    'mode' => $match[1],
    'key' => $_SERVER['HTTP_X_VULQEN_KEY'] ?? null,
    'encoding' => $_SERVER['HTTP_CONTENT_ENCODING'] ?? null,
    'body' => ($_SERVER['HTTP_CONTENT_ENCODING'] ?? '') === 'gzip' ? gzdecode($raw) : $raw,
])."\n", FILE_APPEND | LOCK_EX);

header('Content-Type: application/json');
switch ($match[1]) {
    case '0429':
        http_response_code(429);
        header('Retry-After: 7');
        echo '{"error":"ingest.rate_limited"}';
        break;
    case '0500':
        http_response_code(500);
        break;
    default:
        http_response_code(202);
        echo '{"accepted":1,"duplicates":0,"config":{"version":1,"max_spans":7,"spans_stored":true}}';
}
