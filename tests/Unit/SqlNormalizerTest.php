<?php

declare(strict_types=1);

namespace Vulqen\Sdk\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vulqen\Sdk\SqlNormalizer;

final class SqlNormalizerTest extends TestCase
{
    #[DataProvider('cases')]
    public function testNormalizuje(string $sql, string $expected): void
    {
        self::assertSame($expected, SqlNormalizer::normalize($sql));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function cases(): array
    {
        return [
            'liczby' => ['SELECT * FROM orders WHERE id = 42 AND total > 10.5', 'SELECT * FROM orders WHERE id = ? AND total > ?'],
            'liczba ujemna' => ['SELECT * FROM t WHERE a = -1 AND b IN (-2, 3)', 'SELECT * FROM t WHERE a = ? AND b IN (?)'],
            'odejmowanie zostaje' => ['SELECT a - 1 FROM t', 'SELECT a - ? FROM t'],
            'apostrofy' => ["SELECT * FROM users WHERE email = 'jan@example.com'", 'SELECT * FROM users WHERE email = ?'],
            'apostrof w apostrofach' => ["SELECT 'it''s', 'a\\'b' FROM t", 'SELECT ?, ? FROM t'],
            'in z listą' => ['SELECT * FROM t WHERE id IN (1, 2, 3, 4)', 'SELECT * FROM t WHERE id IN (?)'],
            'in z placeholderami' => ['SELECT * FROM t WHERE id IN (?, ?, ?)', 'SELECT * FROM t WHERE id IN (?)'],
            'daty' => ["SELECT * FROM t WHERE created_at BETWEEN '2026-10-01' AND '2026-10-08 12:00:00'", 'SELECT * FROM t WHERE created_at BETWEEN ? AND ?'],
            'wiele spacji' => ["SELECT  *\n\tFROM   t\r\n WHERE a = 1", 'SELECT * FROM t WHERE a = ?'],
            'komentarz liniowy' => ["SELECT * FROM t -- do usunięcia\nWHERE a = 1", 'SELECT * FROM t WHERE a = ?'],
            'komentarz blokowy' => ['SELECT /* hint */ * FROM t WHERE a = 1', 'SELECT * FROM t WHERE a = ?'],
            'identyfikatory z cyframi' => ['SELECT t1.col2 FROM table3 t1 WHERE t1.id = :p1 AND t1.x = $2', 'SELECT t1.col2 FROM table3 t1 WHERE t1.id = :p1 AND t1.x = $2'],
            'identyfikatory w cudzysłowach' => ['SELECT "col 1", `t2`.`id` FROM `t2`', 'SELECT "col 1", `t2`.`id` FROM `t2`'],
            'hex i wykładnik' => ['SELECT 0xFF, 1.5e-3 FROM t', 'SELECT ?, ? FROM t'],
            'values z wieloma wierszami' => ["INSERT INTO t (a, b) VALUES (1, 'x'), (2, 'y'), (3, 'z')", 'INSERT INTO t (a, b) VALUES (?, ?)'],
            'limit i offset' => ['SELECT * FROM t LIMIT 10 OFFSET 20', 'SELECT * FROM t LIMIT ? OFFSET ?'],
            'dsn z hasłem' => ['SELECT * FROM dblink(mysql://app:tajne@db/x)', 'SELECT * FROM dblink(mysql://***@db/x)'],
            'niedomknięty string' => ["SELECT * FROM t WHERE a = 'x", SqlNormalizer::UNPARSED],
            'niedomknięty komentarz' => ['SELECT /* x FROM t', SqlNormalizer::UNPARSED],
        ];
    }

    public function testDwaLiteralyDajaJedenTekst(): void
    {
        self::assertSame(
            SqlNormalizer::normalize("SELECT * FROM orders WHERE id = 1 AND status = 'new'"),
            SqlNormalizer::normalize("SELECT * FROM orders WHERE id = 987 AND status = 'paid'"),
        );
    }

    public function testDlugiSqlJestObcinany(): void
    {
        $sql = 'SELECT '.implode(', ', array_fill(0, 1000, 'column_name')).' FROM t';

        self::assertSame(SqlNormalizer::MAX_LENGTH, mb_strlen(SqlNormalizer::normalize($sql)));
    }
}
