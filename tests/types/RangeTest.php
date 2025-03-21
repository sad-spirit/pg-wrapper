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
    Range,
    NumericRange,
    DateTimeRange
};

/**
 * Unit test for Range class
 */
class RangeTest extends TestCase
{
    public function testCreateEmpty(): void
    {
        $range = NumericRange::createEmpty();
        $this->assertInstanceOf(NumericRange::class, $range);
        $this->assertTrue($range->empty);
    }

    #[DataProvider('getInvalidNumericRanges')]
    public function testInvalidNumericRangesViaConstructor(int|string $lower, int|string $upper): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NumericRange($lower, $upper);
    }

    public static function getInvalidNumericRanges(): array
    {
        return [
            [2, 1],
            [5, 'a'],
            ['b', 6]
        ];
    }

    public function testInvalidDateTimeRangesViaConstructor(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DateTimeRange(new \DateTime('tomorrow'), new \DateTime('yesterday'));
    }

    public function getInvalidDateTimeRanges(): array
    {
        return [
            [new \DateTime('tomorrow'), new \DateTime('yesterday')],
            [new \DateTime('now'), 'foo'],
            ['bar', new \DateTime('now')]
        ];
    }

    public function testSinglePointDateTimeRange(): void
    {
        $tomorrow    = new \DateTimeImmutable('tomorrow');
        $singlePoint = new DateTimeRange($tomorrow, $tomorrow, true, true);
        $this::assertFalse($singlePoint->empty);

        $emptyRange = new DateTimeRange($tomorrow, $tomorrow, false, true);
        $this::assertTrue($emptyRange->empty);
    }

    public function testSinglePointNumericRange(): void
    {
        $singlePoint = new NumericRange(3, 3, true, true);
        $this::assertFalse($singlePoint->empty);

        $emptyRange = new NumericRange(3, 3, false, true);
        $this::assertTrue($emptyRange->empty);
    }

    public function testDateRangeBoundsAreImmutable(): void
    {
        $range = new DateTimeRange(new \DateTime('yesterday'), new \DateTimeImmutable('tomorrow'));
        $this::assertInstanceOf(\DateTimeImmutable::class, $range->lower);
        $this::assertInstanceOf(\DateTimeImmutable::class, $range->upper);
    }

    #[DataProvider('missingBoundsProvider')]
    public function testMissingBoundIsExclusive(?string $lowerBound, ?string $upperBound): void
    {
        $range = new Range($lowerBound, $upperBound, true, true);
        if (null === $lowerBound) {
            $this::assertFalse($range->lowerInclusive);
        } else {
            $this::assertTrue($range->lowerInclusive);
        }
        if (null === $upperBound) {
            $this::assertFalse($range->upperInclusive);
        } else {
            $this::assertTrue($range->upperInclusive);
        }
    }

    public static function missingBoundsProvider(): array
    {
        return [
            ['a', null],
            [null, 'z'],
            [null, null]
        ];
    }

    #[DataProvider('arrayProvider')]
    public function testCreateFromArray(array $input, NumericRange $expected): void
    {
        $this::assertEquals(
            $expected,
            NumericRange::createFromArray($input)
        );
    }

    public static function arrayProvider(): array
    {
        return [
            [
                ['empty' => true],
                NumericRange::createEmpty()
            ],
            [
                [],
                new NumericRange()
            ],
            [
                [2],
                new NumericRange(2)
            ],
            [
                [2, 3],
                new NumericRange(2, 3)
            ],
            [
                ['upper' => 2, 'upperInclusive' => true],
                new NumericRange(null, 2, false, true)
            ]
        ];
    }
}
