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
use sad_spirit\pg_wrapper\converters\datetime\DateConverter;
use sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Unit test for date type converter
 */
class DateTest extends TestCase
{
    /**
     * @var DateConverter
     */
    protected $caster;

    public function setUp(): void
    {
        $this->caster = new DateConverter();
    }

    /**
     * @dataProvider getValuesFrom
     */
    public function testCastFrom($style, $native, $value)
    {
        if ($value instanceof \Exception) {
            $this->expectException(get_class($value));
        }
        if (null !== $style) {
            $this->caster->setDateStyle($style);
        }
        $this->assertEquals($value, $this->caster->input($native));
    }

    /**
     * @dataProvider getValuesTo
     */
    public function testCastTo($native, $value)
    {
        if ($native instanceof \Exception) {
            $this->expectException(get_class($native));
        }
        $this->assertEquals($native, $this->caster->output($value));
    }

    public function getValuesFrom()
    {
        return [
            [null,             null,         null],
            [null,             '2001-02-03', new \DateTime('2001-02-03')],
            [null,             '1800-01-01', new \DateTime('1800-01-01')],
            ['ISO, MDY',       '2001-02-03', new \DateTime('2001-02-03')],
            ['Postgres, DMY',  '03-02-2001', new \DateTime('2001-02-03')],
            ['Postgres, MDY',  '02-03-2001', new \DateTime('2001-02-03')],
            ['SQL, DMY',       '03/02/2001', new \DateTime('2001-02-03')],
            ['SQL, MDY',       '02/03/2001', new \DateTime('2001-02-03')],
            ['German, YMD',    '03.02.2001', new \DateTime('2001-02-03')]
        ];
    }

    public function getValuesTo()
    {
        $dateTime = new \DateTime('2001-05-25');
        return [
            ['whatever',       'whatever'],
            ['1970-01-01',     0],
            ['2001-05-25',     $dateTime],
            ['2001-05-25',     $dateTime->getTimestamp()],
            ['2001-05-25',     \DateTimeImmutable::createFromMutable($dateTime)],

            [new TypeConversionException(), false],
            [new TypeConversionException(), 1.234],
            [new TypeConversionException(), []],
            [new TypeConversionException(), new \stdClass()]
        ];
    }
}
