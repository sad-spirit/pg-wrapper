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

namespace sad_spirit\pg_wrapper\tests\converters;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use sad_spirit\pg_wrapper\converters\datetime\TimeStampTzConverter;
use sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Unit test for 'timestamp with time zone' type converter
 */
class TimestampTzTest extends TestCase
{
    protected TimeStampTzConverter $caster;

    public function setUp(): void
    {
        $this->caster = new TimeStampTzConverter();
    }

    #[DataProvider('getValuesFrom')]
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

    #[DataProvider('getValuesTo')]
    public function testCastTo(string|\Throwable|null $native, mixed $value): void
    {
        if ($native instanceof \Throwable) {
            $this->expectException($native::class);
        }
        $this->assertEquals($native, $this->caster->output($value));
    }

    public static function getValuesFrom(): array
    {
        return [
            [null,             null,                               null],
            [null,             '2001-02-03 04:05:06+09',           new \DateTime('2001-02-03 04:05:06+09')],
            [null,             '2001-02-03 04:05:06.78+09',        new \DateTime('2001-02-03 04:05:06.78+09')],
            [null,             '2001-02-03 04:05:06',              new TypeConversionException()],
            ['ISO, MDY',       '2001-02-03 04:05:06.78 CET',       new \DateTime('2001-02-03 04:05:06.78 CET')],
            ['Postgres, DMY',  'Sat 03 Feb 04:05:06.78 2001 CET',  new \DateTime('2001-02-03 04:05:06.78 CET')],
            ['Postgres, MDY',  'Sat Feb 03 04:05:06 2001 CET',     new \DateTime('2001-02-03 04:05:06 CET')],
            ['SQL, DMY',       '03/02/2001 04:05:06 CET',          new \DateTime('2001-02-03 04:05:06 CET')],
            ['SQL, MDY',       '02/03/2001 04:05:06.78 CET',       new \DateTime('2001-02-03 04:05:06.78 CET')],
            ['German, YMD',    '03.02.2001 04:05:06 CET',          new \DateTime('2001-02-03 04:05:06 CET')]
        ];
    }

    public static function getValuesTo(): array
    {
        return [
            ['whatever',                       'whatever'],
            ['1970-01-01 00:00:01.000000+0000', 1],
            [
                '2013-01-01 02:03:04.000000+0400',
                new \DateTime('2013-01-01 02:03:04', new \DateTimeZone('Europe/Moscow'))
            ],
            [
                '2013-01-01 02:03:04.000000+0400',
                new \DateTimeImmutable('2013-01-01 02:03:04', new \DateTimeZone('Europe/Moscow'))
            ],
            [new TypeConversionException(),     false],
            [new TypeConversionException(),     1.234],
            [new TypeConversionException(),     []],
            [new TypeConversionException(),     new \stdClass()]
        ];
    }
}
