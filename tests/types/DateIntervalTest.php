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
use sad_spirit\pg_wrapper\types\DateInterval;

/**
 * Unit test for DateInterval class (the one from package, not built-in)
 */
class DateIntervalTest extends TestCase
{
    public function testOutputEmpty()
    {
        $interval = new DateInterval('P0Y');
        $this->assertEquals('PT0S', $interval->__toString());
    }

    public function testOutputFractionalSeconds()
    {
        $interval = new DateInterval('PT1M');
        $interval->f = 0.123;
        $this->assertEquals('PT1M0.123S', $interval->__toString());
    }

    public function testOutputNegative()
    {
        $interval = new DateInterval('PT0S');
        $interval->h = -2;
        $interval->i = -5;
        $this->assertEquals('PT-2H-5M', $interval->__toString());
    }

    public function testOutputInverted()
    {
        $interval = new DateInterval('PT2H5M');
        $interval->invert = true;
        $this->assertEquals('PT-2H-5M', $interval->__toString());
    }
}
