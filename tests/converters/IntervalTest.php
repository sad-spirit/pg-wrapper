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

use sad_spirit\pg_wrapper\converters\datetime\IntervalConverter;
use sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Unit test for interval type converter
 *
 * @extends TypeConverterTestCase<IntervalConverter>
 */
class IntervalTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new IntervalConverter();
    }

    public static function valuesBoth(): array
    {
        return [];
    }

    private static function intervalFromArray(array $fields): \DateInterval
    {
        $interval = new \DateInterval('PT0S');
        foreach ($fields as $k => $v) {
            $interval->$k = $v;
        }
        return $interval;
    }

    public static function valuesFrom(): array
    {
        return [
            [null,                                                 null],

            // directly from PostgreSQL's documentation dealing with interval output
            [
                '1-2',
                self::intervalFromArray(['y' => 1, 'm' => 2])
            ],
            [
                '3 4:05:06',
                self::intervalFromArray(['d' => 3, 'h' => 4, 'i' => 5, 's' => 6])
            ],
            [
                '-1-2 +3 -4:05:06',
                self::intervalFromArray(['y' => -1, 'm' => -2, 'd' => 3, 'h' => -4, 'i' => -5, 's' => -6])
            ],
            [
                '1 year 2 mons',
                self::intervalFromArray(['y' => 1, 'm' => 2])
            ],
            [
                '3 days 04:05:06',
                self::intervalFromArray(['d' => 3, 'h' => 4, 'i' => 5, 's' => 6])
            ],
            [
                '-1 year -2 mons +3 days -04:05:06',
                self::intervalFromArray(['y' => -1, 'm' => -2, 'd' => 3, 'h' => -4, 'i' => -5, 's' => -6])
            ],
            [
                '@ 1 year 2 mons',
                self::intervalFromArray(['y' => 1, 'm' => 2])
            ],
            [
                '@ 3 days 4 hours 5 mins 6 secs',
                self::intervalFromArray(['d' => 3, 'h' => 4, 'i' => 5, 's' => 6])
            ],
            [
                '@ 1 year 2 mons -3 days 4 hours 5 mins 6 secs ago',
                self::intervalFromArray(['y' => 1, 'm' => 2, 'd' => -3, 'h' => 4, 'i' => 5, 's' => 6, 'invert' => true])
            ],
            [
                'P1Y2M',
                self::intervalFromArray(['y' => 1, 'm' => 2])
            ],
            [
                'P3DT4H5M6S',
                self::intervalFromArray(['d' => 3, 'h' => 4, 'i' => 5, 's' => 6])
            ],
            [
                'P-1Y-2M3DT-4H-5M-6S',
                self::intervalFromArray(['y' => -1, 'm' => '-2', 'd' => 3, 'h' => -4, 'i' => -5, 's' => -6])
            ],
            [
                'P1W-2D',
                self::intervalFromArray(['d' => 5])
            ],
            // handling whitespace
            [
                "\v\f1\ryear\n2\v\tmonths\v\f",
                self::intervalFromArray(['y' => 1, 'm' => 2])
            ],

            // handling of fractional seconds
            [
                '01:02:03.456',
                self::intervalFromArray(['h' => 1, 'i' => 2, 's' => 3, 'f' => .456])
            ],
            [
                '@ 1 hour 2 mins 3.456 secs ago',
                self::intervalFromArray(['h' => 1, 'i' => 2, 's' => 3, 'f' => .456, 'invert' => true])
            ],
            [
                'PT1H2M-3.456S',
                self::intervalFromArray(['h' => 1, 'i' => 2, 's' => -3, 'f' => -.456])
            ],

            // Postgres allows this
            [
                '10',
                self::intervalFromArray(['s' => 10])
            ],
            // time specification with two fields, note the difference
            [
                '1:2',
                self::intervalFromArray(['h' => 1, 'i' => 2])
            ],
            [
                '3:4.5',
                self::intervalFromArray(['i' => 3, 's' => 4, 'f' => .5])
            ],

            // invalid input
            ['blah-blah',                                          new TypeConversionException()],
            ['1001 nights',                                        new TypeConversionException()],
            ['P1001N',                                             new TypeConversionException()],
            ['3.5 days',                                           new TypeConversionException()],
            ['P3.5D',                                              new TypeConversionException()],

            // Postgres does not allow empty interval strings, we shouldn't either
            ['',                                                   new TypeConversionException()],
            ['@',                                                  new TypeConversionException()]
        ];
    }

    public static function valuesTo(): array
    {
        $frac = new \DateInterval('PT1H2M3S');
        $frac->f = 0.45;

        $inv  = new \DateInterval('P1Y2M');
        $inv->invert = 1;

        $neg  = new \DateInterval('PT0S');
        $neg->y = -1;
        $neg->m = -2;

        return [
            [null,                          null],
            ['10 seconds',                  10],
            ['-12.34 seconds',              -12.34],
            ['20 seconds',                  20.0],
            ['0.5 seconds',                 0.5],
            ['whatever',                    'whatever'],
            ['PT10S',                       'PT10S'],
            ['PT0S',                        new \DateInterval('P0Y')],
            ['P1Y2M',                       new \DateInterval('P1Y2M')],
            ['PT1H2M3.45S',                 $frac],
            ['P-1Y-2M',                     $inv],
            ['P-1Y-2M',                     $neg],
            [new TypeConversionException(), false],
            [new TypeConversionException(), new \stdClass()],
            [new TypeConversionException(), []]
        ];
    }
}
