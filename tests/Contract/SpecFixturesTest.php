<?php

declare(strict_types=1);

namespace Vulqen\Sdk\Tests\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vulqen\Sdk\Tests\Support\Spec;

/**
 * Kontrakt envelope z vulqen/spec (05, D-027). Serializer jest sprawdzany tą samą schema w EnvelopeSerializerTest.
 */
final class SpecFixturesTest extends TestCase
{
    #[DataProvider('validFixtures')]
    public function testPoprawnyFixturePrzechodzi(string $file): void
    {
        $violations = Spec::violations(self::load($file));

        self::assertNull($violations, basename($file).' powinien przechodzić: '.$violations);
    }

    #[DataProvider('invalidFixtures')]
    public function testBlednyFixturePada(string $file): void
    {
        self::assertNotNull(Spec::violations(self::load($file)), basename($file).' powinien paść.');
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
        foreach (glob(Spec::directory().'/fixtures/'.$group.'/*.json') ?: [] as $file) {
            $cases[basename($file)] = [$file];
        }
        if ($cases === []) {
            throw new \RuntimeException(sprintf('Brak fixtures %s w %s.', $group, Spec::directory()));
        }

        return $cases;
    }

    private static function load(string $file): object
    {
        $decoded = json_decode((string) file_get_contents($file));
        self::assertInstanceOf(\stdClass::class, $decoded, basename($file).' nie jest obiektem JSON.');

        return $decoded;
    }
}
