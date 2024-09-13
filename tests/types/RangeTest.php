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
 * @copyright 2014-2024 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\tests\types;

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

    /**
     * @dataProvider getInvalidNumericRanges
     * @param mixed $lower
     * @param mixed $upper
     */
    public function testInvalidNumericRangesViaConstructor($lower, $upper): void
    {
        $this->expectException(InvalidArgumentException::class);

        $range = new NumericRange($lower, $upper);
    }

    public function getInvalidNumericRanges(): array
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

        $range = new DateTimeRange(new \DateTime('tomorrow'), new \DateTime('yesterday'));
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

    /**
     * @dataProvider missingBoundsProvider
     * @param ?string $lowerBound
     * @param ?string $upperBound
     */
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

    public function missingBoundsProvider(): array
    {
        return [
            ['a', null],
            [null, 'z'],
            [null, null]
        ];
    }

    /**
     * @dataProvider arrayProvider
     * @param array $input
     * @param NumericRange $expected
     */
    public function testCreateFromArray(array $input, NumericRange $expected): void
    {
        $this::assertEquals(
            $expected,
            NumericRange::createFromArray($input)
        );
    }

    public function arrayProvider(): array
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
