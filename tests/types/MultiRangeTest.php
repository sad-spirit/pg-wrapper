<?php

/*
 * This file is part of sad_spirit/pg_wrapper:
 * converter of complex PostgreSQL types and an OO wrapper for PHP's pgsql extension.
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\tests\types;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use sad_spirit\pg_wrapper\exceptions\InvalidArgumentException;
use sad_spirit\pg_wrapper\types\{
    DateTimeMultiRange,
    DateTimeRange,
    MultiRange,
    NumericMultiRange,
    NumericRange,
    Range
};

/**
 * Unit tests for MultiRange class and its subclasses
 */
class MultiRangeTest extends TestCase
{
    public function testCreateEmpty(): void
    {
        $multiRange = new MultiRange();
        $this::assertCount(0, $multiRange);
        $this::assertEquals(new \ArrayIterator([]), $multiRange->getIterator());
    }

    public function testCreateNumericMultiRange(): void
    {
        $range      = new NumericRange(1, 2);
        $multiRange = new NumericMultiRange($range);
        $this::assertCount(1, $multiRange);
        $this::assertSame($range, $multiRange[0]);
        $this::assertFalse(isset($multiRange[1]));
    }

    #[DataProvider('invalidDateTimeMultiRangeProvider')]
    public function testInvalidDateTimeMultiRangeViaConstructor(array $ranges): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('only instances of ' . DateTimeRange::class);
        new DateTimeMultiRange(...$ranges);
    }

    #[DataProvider('invalidDateTimeMultiRangeProvider')]
    public function testInvalidDateTimeMultiRangeViaCreateFromArray(array $ranges): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('compatible Ranges or arrays');
        DateTimeMultiRange::createFromArray($ranges);
    }

    public static function invalidDateTimeMultiRangeProvider(): array
    {
        return [
            [[new Range()]],
            [[new NumericRange()]],
            [[new DateTimeRange(), new NumericRange()]]
        ];
    }
}
