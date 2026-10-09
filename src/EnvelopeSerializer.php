<?php

declare(strict_types=1);

namespace Vulqen\Sdk;

/**
 * Składa envelope v1 (20). Elementy są kodowane osobno, więc rozmiar paczki da się policzyć bez drugiego json_encode.
 */
final class EnvelopeSerializer
{
    public const MAX_ITEMS = 100;
    /** Zapas pod limitem 4 MiB po rozpakowaniu na serwerze (16). */
    public const MAX_BYTES = 3_145_728;

    private const FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR;

    public function __construct(private readonly Options $options)
    {
    }

    /**
     * @param array<string, mixed> $item
     *
     * @throws \JsonException
     */
    public static function encodeItem(array $item): string
    {
        return json_encode($item, self::FLAGS);
    }

    /**
     * @param list<string> $encodedItems
     *
     * @return list<string> po jednym JSON na żądanie HTTP, każdy z 1 do 100 elementami
     *
     * @throws \JsonException
     */
    public function envelopes(array $encodedItems, float $sentAt): array
    {
        $head = $this->head($sentAt);
        $envelopes = [];
        $batch = [];
        $bytes = strlen($head);

        foreach ($encodedItems as $item) {
            $size = strlen($item) + 1;
            if ($batch !== [] && (count($batch) >= self::MAX_ITEMS || $bytes + $size > self::MAX_BYTES)) {
                $envelopes[] = $head.implode(',', $batch).']}';
                $batch = [];
                $bytes = strlen($head);
            }
            $batch[] = $item;
            $bytes += $size;
        }
        if ($batch !== []) {
            $envelopes[] = $head.implode(',', $batch).']}';
        }

        return $envelopes;
    }

    /**
     * @throws \JsonException
     */
    private function head(float $sentAt): string
    {
        $meta = [
            'v' => 1,
            'sdk' => ['name' => $this->options->sdkName, 'version' => $this->options->sdkVersion],
            'sent_at' => Timestamp::format($sentAt),
            'environment' => $this->options->environment,
        ];
        if ($this->options->release !== null) {
            $meta['release'] = $this->options->release;
        }
        if ($this->options->serverName !== null) {
            $meta['server_name'] = $this->options->serverName;
        }

        return substr(json_encode($meta, self::FLAGS), 0, -1).',"items":[';
    }
}
