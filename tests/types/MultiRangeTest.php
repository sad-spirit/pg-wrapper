<?php

/**
 * Converter of complex PostgreSQL types and an OO wrapper for PHP's pgsql extension
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-wrapper/master/LICENSE
 *
 * @package   sad_spirit\pg_wrapper
 * @copyright 2014-2023 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\tests\types;

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

    /**
     * @dataProvider invalidDateTimeMultiRangeProvider
     * @param array $ranges
     */
    public function testInvalidDateTimeMultiRangeViaConstructor(array $ranges): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('only instances of ' . DateTimeRange::class);
        new DateTimeMultiRange(...$ranges);
    }

    /**
     * @dataProvider invalidDateTimeMultiRangeProvider
     * @param array $ranges
     */
    public function testInvalidDateTimeMultiRangeViaCreateFromArray(array $ranges): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('compatible Ranges or arrays');
        DateTimeMultiRange::createFromArray($ranges);
    }

    public function invalidDateTimeMultiRangeProvider(): array
    {
        return [
            [[new Range()]],
            [[new NumericRange()]],
            [[new DateTimeRange(), new NumericRange()]]
        ];
    }
}
