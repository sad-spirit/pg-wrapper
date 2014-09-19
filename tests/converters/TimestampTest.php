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

namespace sad_spirit\pg_wrapper\tests\converters;

use sad_spirit\pg_wrapper\converters\datetime\TimeStampConverter,
    sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Unit test for timestamp (without time zone) type converter
 */
class TimestampTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var TimeStampConverter
     */
    protected $caster;

    public function setUp()
    {
        $this->caster = new TimeStampConverter();
    }

    /**
     * @dataProvider getValuesFrom
     */
    public function testCastFrom($style, $native, $value)
    {
        if ($value instanceof \Exception) {
            $this->setExpectedException(get_class($value));
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
            $this->setExpectedException(get_class($native));
        }
        $this->assertEquals($native, $this->caster->output($value));
    }

    public function getValuesFrom()
    {
        return array(
            array(null,             null,                           null),
            array(null,             '2001-02-03 04:05:06.78',       new \DateTime('2001-02-03 04:05:06.78')),
            array(null,             '2001-02-03 04:05:06',          new \DateTime('2001-02-03 04:05:06')),
            array(null,             '2001-02-03 04:05:06+09',       new TypeConversionException()),
            array('ISO, MDY',       '2001-02-03 04:05:06.78',       new \DateTime('2001-02-03 04:05:06.78')),
            array('Postgres, DMY',  'Sat 03 Feb 04:05:06.78 2001',  new \DateTime('2001-02-03 04:05:06.78')),
            array('Postgres, MDY',  'Sat Feb 03 04:05:06 2001',     new \DateTime('2001-02-03 04:05:06')),
            array('SQL, DMY',       '03/02/2001 04:05:06',          new \DateTime('2001-02-03 04:05:06')),
            array('SQL, MDY',       '02/03/2001 04:05:06.78',       new \DateTime('2001-02-03 04:05:06.78')),
            array('German, YMD',    '03.02.2001 04:05:06',          new \DateTime('2001-02-03 04:05:06'))
        );
    }

    public function getValuesTo()
    {
        $dateTime = new \DateTime('2001-02-03 04:05:06');
        return array(
            array(null,                             null),
            array('whatever',                       'whatever'),
            array('1970-01-01 00:00:00.000000',     0),
            array('2001-02-03 04:05:06.000000',     $dateTime),
            array('2001-02-03 04:05:06.000000',     $dateTime->getTimestamp()),
            array(new TypeConversionException(),    false),
            array(new TypeConversionException(),    1.234),
            array(new TypeConversionException(),    array()),
            array(new TypeConversionException(),    new \stdClass())
        );
    }
}