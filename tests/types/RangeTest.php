<?php

/**
 * Wrapper for PHP's pgsql extension providing conversion of complex DB types
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-wrapper/master/LICENSE
 *
 * @package   sad_spirit\pg_wrapper
 * @copyright 2014-2020 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
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
    public function testDisallowExtraProperties()
    {
        $this->expectException(InvalidArgumentException::class);

        $range = new Range();
        $range->foo = 'bar';
    }

    public function testCreateEmpty()
    {
        $range = NumericRange::createEmpty();
        $this->assertInstanceOf(NumericRange::class, $range);
        $this->assertTrue($range->empty);
    }

    /**
     * @dataProvider getInvalidNumericRanges
     */
    public function testInvalidNumericRangesViaConstructor($lower, $upper)
    {
        $this->expectException(InvalidArgumentException::class);

        $range = new NumericRange($lower, $upper);
    }

    /**
     * @dataProvider getInvalidNumericRanges
     */
    public function testInvalidNumericRangesViaProperties($lower, $upper)
    {
        $this->expectException(InvalidArgumentException::class);

        $range = new NumericRange();
        $range->lower = $lower;
        $range->upper = $upper;
    }

    public function getInvalidNumericRanges()
    {
        return [
            [2, 1],
            [5, 'a'],
            ['b', 6]
        ];
    }

    public function testInvalidDateTimeRangesViaConstructor()
    {
        $this->expectException(InvalidArgumentException::class);

        $range = new DateTimeRange(new \DateTime('tomorrow'), new \DateTime('yesterday'));
    }

    /**
     * @dataProvider getInvalidDateTimeRanges
     */
    public function testInvalidDateTimeRangesViaProperties($lower, $upper)
    {
        $this->expectException(InvalidArgumentException::class);

        $range = new DateTimeRange();
        $range->lower = $lower;
        $range->upper = $upper;
    }

    public function getInvalidDateTimeRanges()
    {
        return [
            [new \DateTime('tomorrow'), new \DateTime('yesterday')],
            [new \DateTime('now'), 'foo'],
            ['bar', new \DateTime('now')]
        ];
    }
}
