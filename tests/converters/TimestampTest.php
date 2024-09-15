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

namespace sad_spirit\pg_wrapper\tests\converters;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_wrapper\converters\datetime\TimeStampConverter;
use sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Unit test for timestamp (without time zone) type converter
 */
class TimestampTest extends TestCase
{
    protected TimeStampConverter $caster;

    public function setUp(): void
    {
        $this->caster = new TimeStampConverter();
    }

    /**
     * @dataProvider getValuesFrom
     */
    public function testCastFrom(?string $style, ?string $native, \DateTimeInterface|\Throwable|null $value): void
    {
        if ($value instanceof \Throwable) {
            $this->expectException($value::class);
        }
        if (null !== $style) {
            $this->caster->setDateStyle($style);
        }
        $this->assertEquals($value, $this->caster->input($native));
    }

    /**
     * @dataProvider getValuesTo
     */
    public function testCastTo(string|\Throwable|null $native, mixed $value): void
    {
        if ($native instanceof \Throwable) {
            $this->expectException($native::class);
        }
        $this->assertEquals($native, $this->caster->output($value));
    }

    public function getValuesFrom(): array
    {
        return [
            [null,             null,                           null],
            [null,             '2001-02-03 04:05:06.78',       new \DateTime('2001-02-03 04:05:06.78')],
            [null,             '2001-02-03 04:05:06',          new \DateTime('2001-02-03 04:05:06')],
            [null,             '2001-02-03 04:05:06+09',       new TypeConversionException()],
            ['ISO, MDY',       '2001-02-03 04:05:06.78',       new \DateTime('2001-02-03 04:05:06.78')],
            ['Postgres, DMY',  'Sat 03 Feb 04:05:06.78 2001',  new \DateTime('2001-02-03 04:05:06.78')],
            ['Postgres, MDY',  'Sat Feb 03 04:05:06 2001',     new \DateTime('2001-02-03 04:05:06')],
            ['SQL, DMY',       '03/02/2001 04:05:06',          new \DateTime('2001-02-03 04:05:06')],
            ['SQL, MDY',       '02/03/2001 04:05:06.78',       new \DateTime('2001-02-03 04:05:06.78')],
            ['German, YMD',    '03.02.2001 04:05:06',          new \DateTime('2001-02-03 04:05:06')]
        ];
    }

    public function getValuesTo(): array
    {
        $dateTime = new \DateTime('2001-02-03 04:05:06');
        return [
            [null,                             null],
            ['whatever',                       'whatever'],
            ['1970-01-01 00:00:00.000000',     0],
            ['2001-02-03 04:05:06.000000',     $dateTime],
            ['2001-02-03 04:05:06.000000',     $dateTime->getTimestamp()],
            ['2001-02-03 04:05:06.000000',     \DateTimeImmutable::createFromMutable($dateTime)],
            [new TypeConversionException(),    false],
            [new TypeConversionException(),    1.234],
            [new TypeConversionException(),    []],
            [new TypeConversionException(),    new \stdClass()]
        ];
    }
}
