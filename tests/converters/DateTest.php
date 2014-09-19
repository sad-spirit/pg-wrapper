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

use sad_spirit\pg_wrapper\converters\datetime\DateConverter,
    sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Unit test for date type converter
 */
class DateTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var DateConverter
     */
    protected $caster;

    public function setUp()
    {
        $this->caster = new DateConverter;
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
            array(null,             null,         null),
            array(null,             '2001-02-03', new \DateTime('2001-02-03')),
            array(null,             '1800-01-01', new \DateTime('1800-01-01')),
            array('ISO, MDY',       '2001-02-03', new \DateTime('2001-02-03')),
            array('Postgres, DMY',  '03-02-2001', new \DateTime('2001-02-03')),
            array('Postgres, MDY',  '02-03-2001', new \DateTime('2001-02-03')),
            array('SQL, DMY',       '03/02/2001', new \DateTime('2001-02-03')),
            array('SQL, MDY',       '02/03/2001', new \DateTime('2001-02-03')),
            array('German, YMD',    '03.02.2001', new \DateTime('2001-02-03'))
        );
    }

    public function getValuesTo()
    {
        $dateTime = new \DateTime('2001-05-25');
        return array(
            array('whatever',       'whatever'),
            array('1970-01-01',     0),
            array('2001-05-25',     $dateTime),
            array('2001-05-25',     $dateTime->getTimestamp()),

            array(new TypeConversionException(), false),
            array(new TypeConversionException(), 1.234),
            array(new TypeConversionException(), array()),
            array(new TypeConversionException(), new \stdClass())
        );
    }
}
