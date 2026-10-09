<?php

declare(strict_types=1);

namespace Vulqen\Sdk\Tests\Support;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

/**
 * Schema z vulqen/spec (05, D-027, D-071): VULQEN_SPEC_DIR, lokalnie ../vulqen/spec, kopia tests/fixtures/spec.
 */
final class Spec
{
    public const SCHEMA_ID = 'https://vulqen.example/spec/envelope.v1.schema.json';

    private static ?Validator $validator = null;

    public static function directory(): string
    {
        $candidates = [
            (string) getenv('VULQEN_SPEC_DIR'),
            dirname(__DIR__, 3).'/vulqen/spec',
            dirname(__DIR__).'/fixtures/spec',
        ];
        foreach ($candidates as $candidate) {
            if ($candidate !== '' && is_file($candidate.'/envelope.v1.schema.json')) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Brak spec: ustaw VULQEN_SPEC_DIR, trzymaj ../vulqen obok tego repozytorium albo tests/fixtures/spec.');
    }

    /**
     * Zwraca null, gdy dokument przechodzi schema, albo opis błędów.
     */
    public static function violations(object $document): ?string
    {
        $error = self::validator()->validate($document, self::SCHEMA_ID)->error();

        return $error === null ? null : (string) json_encode((new ErrorFormatter())->format($error));
    }

    private static function validator(): Validator
    {
        if (self::$validator === null) {
            self::$validator = new Validator();
            self::$validator->resolver()?->registerFile(self::SCHEMA_ID, self::directory().'/envelope.v1.schema.json');
        }

        return self::$validator;
    }
}
