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

namespace sad_spirit\pg_wrapper\tests\converters;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_wrapper\converters\datetime\TimeStampTzConverter;
use sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Unit test for 'timestamp with time zone' type converter
 */
class TimestampTzTest extends TestCase
{
    /**
     * @var TimeStampTzConverter
     */
    protected $caster;

    public function setUp(): void
    {
        $this->caster = new TimeStampTzConverter();
    }

    /**
     * @dataProvider getValuesFrom
     * @param string|null                        $style
     * @param string|null                        $native
     * @param \DateTimeInterface|\Throwable|null $value
     */
    public function testCastFrom(?string $style, ?string $native, $value): void
    {
        if ($value instanceof \Throwable) {
            $this->expectException(get_class($value));
        }
        if (null !== $style) {
            $this->caster->setDateStyle($style);
        }
        $this->assertEquals($value, $this->caster->input($native));
    }

    /**
     * @dataProvider getValuesTo
     * @param string|null|\Throwable $native
     * @param mixed                  $value
     */
    public function testCastTo($native, $value): void
    {
        if ($native instanceof \Throwable) {
            $this->expectException(get_class($native));
        }
        $this->assertEquals($native, $this->caster->output($value));
    }

    public function getValuesFrom(): array
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

    public function getValuesTo(): array
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
