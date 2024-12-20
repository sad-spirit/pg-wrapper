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
