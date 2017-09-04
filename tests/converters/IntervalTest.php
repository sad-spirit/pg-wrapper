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
 * @copyright 2014-2017 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

namespace sad_spirit\pg_wrapper\tests\converters;

use sad_spirit\pg_wrapper\types\DateInterval,
    sad_spirit\pg_wrapper\converters\datetime\IntervalConverter,
    sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Unit test for interval type converter
 */
class IntervalTest extends TypeConverterTestCase
{
    public function setUp()
    {
        $this->converter = new IntervalConverter;
    }

    protected function valuesBoth()
    {
        return array();
    }

    protected function valuesFrom()
    {
        return array(
            array(null,                                                 null),

            // directly from PostgreSQL's documentation dealing with interval output
            array('1-2',                                                'P1Y2M'),
            array('3 4:05:06',                                          'P3DT4H5M6S'),
            array('-1-2 +3 -4:05:06',                                   'P-1Y-2M3DT-4H-5M-6S'),
            array('1 year 2 mons',                                      'P1Y2M'),
            array('3 days 04:05:06',                                    'P3DT4H5M6S'),
            array('-1 year -2 mons +3 days -04:05:06',                  'P-1Y-2M3DT-4H-5M-6S'),
            array('@ 1 year 2 mons',                                    'P1Y2M'),
            array('@ 3 days 4 hours 5 mins 6 secs',                     'P3DT4H5M6S'),
            array('@ 1 year 2 mons -3 days 4 hours 5 mins 6 secs ago',  'P-1Y-2M3DT-4H-5M-6S'),
            array('P1Y2M',                                              'P1Y2M'),
            array('P3DT4H5M6S',                                         'P3DT4H5M6S'),
            array('P-1Y-2M3DT-4H-5M-6S',                                'P-1Y-2M3DT-4H-5M-6S'),

            // handling of fractional seconds
            array('01:02:03.456',                                       'PT1H2M3.456S'),
            array('@ 1 hour 2 mins 3.456 secs ago',                     'PT-1H-2M-3.456S'),
            array('PT1H2M-3.456S',                                      'PT1H2M-3.456S'),

            // invalid input
            array('blah-blah',                                          new TypeConversionException()),
            array('1001 nights',                                        new TypeConversionException()),
            array('P1001N',                                             new TypeConversionException()),
            array('3.5 days',                                           new TypeConversionException()),
            array('P3.5D',                                              new TypeConversionException())
        );
    }

    protected function valuesTo()
    {
        $frac = new DateInterval('PT1H2M3S');
        $frac->fsec = 0.45;

        $inv  = new DateInterval('P1Y2M');
        $inv->invert = true;

        $neg  = new DateInterval('PT0S');
        $neg->y = -1;
        $neg->m = -2;

        return array(
            array(null,                          null),
            array('10 seconds',                  10),
            array('-12.34 seconds',              -12.34),
            array('0.5 seconds',                 0.5),
            array('whatever',                    'whatever'),
            array('PT10S',                       'PT10S'),
            array('PT0S',                        new DateInterval('P0Y')),
            array('P1Y2M',                       new \DateInterval('P1Y2M')),
            array('PT1H2M3.45S',                 $frac),
            array('P-1Y-2M',                     $inv),
            array('P-1Y-2M',                     $neg),
            array(new TypeConversionException(), false),
            array(new TypeConversionException(), new \stdClass()),
            array(new TypeConversionException(), array())
        );
    }
}


