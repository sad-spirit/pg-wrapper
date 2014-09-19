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
 * @copyright 2014 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

namespace sad_spirit\pg_wrapper\tests\types;

use sad_spirit\pg_wrapper\types\Range,
    sad_spirit\pg_wrapper\types\NumericRange,
    sad_spirit\pg_wrapper\types\DateTimeRange;

/**
 * Unit test for Range class
 */
class RangeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \sad_spirit\pg_wrapper\exceptions\InvalidArgumentException
     */
    public function testDisallowExtraProperties()
    {
        $range = new Range();
        $range->foo = 'bar';
    }

    public function testCreateEmpty()
    {
        $range = NumericRange::createEmpty();
        $this->assertInstanceOf('\sad_spirit\pg_wrapper\types\NumericRange', $range);
        $this->assertTrue($range->empty);
    }

    /**
     * @dataProvider getInvalidNumericRanges
     * @expectedException \sad_spirit\pg_wrapper\exceptions\InvalidArgumentException
     */
    public function testInvalidNumericRangesViaConstructor($lower, $upper)
    {
        $range = new NumericRange($lower, $upper);
    }

    /**
     * @dataProvider getInvalidNumericRanges
     * @expectedException \sad_spirit\pg_wrapper\exceptions\InvalidArgumentException
     */
    public function testInvalidNumericRangesViaProperties($lower, $upper)
    {
        $range = new NumericRange();
        $range->lower = $lower;
        $range->upper = $upper;
    }

    public function getInvalidNumericRanges()
    {
        return array(
            array(2, 1),
            array(5, 'a'),
            array('b', 6)
        );
    }

    /**
     * @expectedException \sad_spirit\pg_wrapper\exceptions\InvalidArgumentException
     */
    public function testInvalidDateTimeRangesViaConstructor()
    {
        $range = new DateTimeRange(new \DateTime('tomorrow'), new \DateTime('yesterday'));
    }

    /**
     * @dataProvider getInvalidDateTimeRanges
     * @expectedException \sad_spirit\pg_wrapper\exceptions\InvalidArgumentException
     */
    public function testInvalidDateTimeRangesViaProperties($lower, $upper)
    {
        $range = new DateTimeRange();
        $range->lower = $lower;
        $range->upper = $upper;
    }

    public function getInvalidDateTimeRanges()
    {
        return array(
            array(new \DateTime('tomorrow'), new \DateTime('yesterday')),
            array(new \DateTime('now'), 'foo'),
            array('bar', new \DateTime('now'))
        );
    }
}