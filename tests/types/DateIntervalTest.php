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
use sad_spirit\pg_wrapper\types\DateInterval;

/**
 * Unit test for DateInterval class (the one from package, not built-in)
 */
class DateIntervalTest extends TestCase
{
    public function testOutputEmpty(): void
    {
        $interval = new DateInterval('P0Y');
        $this->assertEquals('PT0S', $interval->__toString());
    }

    public function testOutputFractionalSeconds(): void
    {
        $interval = new DateInterval('PT1M');
        $interval->f = 0.123;
        $this->assertEquals('PT1M0.123S', $interval->__toString());
    }

    public function testOutputNegative(): void
    {
        $interval = new DateInterval('PT0S');
        $interval->h = -2;
        $interval->i = -5;
        $this->assertEquals('PT-2H-5M', $interval->__toString());
    }

    public function testOutputInverted(): void
    {
        $interval = new DateInterval('PT2H5M');
        $interval->invert = 1;
        $this->assertEquals('PT-2H-5M', $interval->__toString());
    }
}
