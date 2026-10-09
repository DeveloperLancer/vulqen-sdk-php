<?php

declare(strict_types=1);

namespace Vulqen\Sdk\Tests\Contract;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Kontrakt envelope z vulqen/spec (05, D-027). Lokalnie czyta ../vulqen/spec,
 * w CI katalog sklonowany na tagu VULQEN_SPEC_REF i wskazany w VULQEN_SPEC_DIR.
 * Serializer z Fazy 4 będzie sprawdzany tą samą schema.
 */
final class SpecFixturesTest extends TestCase
{
    private const SCHEMA_ID = 'https://vulqen.example/spec/envelope.v1.schema.json';

    private static ?Validator $validator = null;

    #[DataProvider('validFixtures')]
    public function testPoprawnyFixturePrzechodzi(string $file): void
    {
        $result = self::validator()->validate(self::load($file), self::SCHEMA_ID);

        $error = $result->error();
        self::assertTrue(
            $result->isValid(),
            basename($file).' powinien przechodzić: '.json_encode($error === null ? null : (new ErrorFormatter())->format($error)),
        );
    }

    #[DataProvider('invalidFixtures')]
    public function testBlednyFixturePada(string $file): void
    {
        self::assertFalse(self::validator()->validate(self::load($file), self::SCHEMA_ID)->isValid(), basename($file).' powinien paść.');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validFixtures(): array
    {
        return self::files('valid');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidFixtures(): array
    {
        return self::files('invalid');
    }

    /**
     * @return array<string, array{string}>
     */
    private static function files(string $group): array
    {
        $cases = [];
        foreach (glob(self::specDirectory().'/fixtures/'.$group.'/*.json') ?: [] as $file) {
            $cases[basename($file)] = [$file];
        }
        if ($cases === []) {
            throw new \RuntimeException(sprintf('Brak fixtures %s w %s.', $group, self::specDirectory()));
        }

        return $cases;
    }

    private static function specDirectory(): string
    {
        $candidates = [
            (string) getenv('VULQEN_SPEC_DIR'),
            dirname(__DIR__, 3).'/vulqen/spec',
        ];
        foreach ($candidates as $candidate) {
            if ($candidate !== '' && is_file($candidate.'/envelope.v1.schema.json')) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Brak spec: ustaw VULQEN_SPEC_DIR albo trzymaj ../vulqen obok tego repozytorium.');
    }

    private static function validator(): Validator
    {
        if (self::$validator === null) {
            self::$validator = new Validator();
            self::$validator->resolver()?->registerFile(self::SCHEMA_ID, self::specDirectory().'/envelope.v1.schema.json');
        }

        return self::$validator;
    }

    private static function load(string $file): object
    {
        $decoded = json_decode((string) file_get_contents($file));
        self::assertInstanceOf(\stdClass::class, $decoded, basename($file).' nie jest obiektem JSON.');

        return $decoded;
    }
}
