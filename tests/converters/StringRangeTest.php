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

use sad_spirit\pg_wrapper\converters\containers\RangeConverter,
    sad_spirit\pg_wrapper\converters\StringConverter,
    sad_spirit\pg_wrapper\exceptions\TypeConversionException,
    sad_spirit\pg_wrapper\types\Range;

/**
 * Unit test for a string-based range type converter
 *
 * Postgres does not have a built-in string range type, but one can be created with "create type"
 */
class StringRangeTest extends TypeConverterTestCase
{
    public function setUp()
    {
        $this->converter = new RangeConverter(new StringConverter());
    }

    protected function valuesBoth()
    {
        return array(
            array('empty',               Range::createEmpty()),
            array('("два","раз"]',       new Range('два', 'раз', false, true))
        );
    }

    protected function valuesFrom()
    {
        return array(
            // tests from rangetypes.sql
            array('-[a,z)',   new TypeConversionException()),
            array('[a,z) - ', new TypeConversionException()),
            array('(",a)',    new TypeConversionException()),
            array('(,,a)',    new TypeConversionException()),
            array('(),a)',    new TypeConversionException()),
            array('(a,))',    new TypeConversionException()),
            array('(],a)',    new TypeConversionException()),
            array('(a,])',    new TypeConversionException()),

            array('  empty  ',                       Range::createEmpty()),
            array(' ( empty, empty )  ',             new Range(' empty', ' empty ', false, false)),
            array(' ( " a " " a ", " z " " z " )  ', new Range('  a   a ', '  z   z  ', false, false)),
            array('(,z)',                            new Range(null, 'z', false, false)),
            array('(a,)',                            new Range('a', null, false, false)),
            array('[,z]',                            new Range(null, 'z', true, true)),
            array('[a,]',                            new Range('a', null, true, true)),
            array('(,)',                             new Range(null, null, false, false)),
            array('[ , ]',                           new Range(' ', ' ', true, true)),
            array('["",""]',                         new Range('', '', true, true)),
            array('[",",","]',                       new Range(',', ',', true, true)),
            array('["\\\\","\\\\"]',                 new Range('\\', '\\', true, true)),
            array('(\\\\,a)',                        new Range('\\', 'a', false, false)),
            array('((,z)',                           new Range('(', 'z', false, false)),
            array('([,z)',                           new Range('[', 'z', false, false)),
            array('(!,()',                           new Range('!', '(', false, false)),
            array('(!,[)',                           new Range('!', '[', false, false)),
            array('[a,a]',                           new Range('a', 'a', true, true)),

            // additional tests
            array('("a","bc\\\\\\""""]',             new Range('a', 'bc\\""', false, true)),
        );
    }

    protected function valuesTo()
    {
        return array(
            array('("a","bc\\\\\\"\\""]',            new Range('a', 'bc\\""', false, true)),
            array('["a","z")',                       array('a', 'z'))
        );
    }
}