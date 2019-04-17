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

use sad_spirit\pg_wrapper\converters\{
    containers\RangeConverter,
    StringConverter
};
use sad_spirit\pg_wrapper\{
    exceptions\TypeConversionException,
    types\Range
};

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
        return [
            ['empty',               Range::createEmpty()],
            ['("два","раз"]',       new Range('два', 'раз', false, true)]
        ];
    }

    protected function valuesFrom()
    {
        return [
            // tests from rangetypes.sql
            ['-[a,z)',   new TypeConversionException()],
            ['[a,z) - ', new TypeConversionException()],
            ['(",a)',    new TypeConversionException()],
            ['(,,a)',    new TypeConversionException()],
            ['(),a)',    new TypeConversionException()],
            ['(a,))',    new TypeConversionException()],
            ['(],a)',    new TypeConversionException()],
            ['(a,])',    new TypeConversionException()],
            ['( \\',     new TypeConversionException()],

            ['  empty  ',                       Range::createEmpty()],
            [' ( empty, empty )  ',             new Range(' empty', ' empty ', false, false)],
            [' ( " a " " a ", " z " " z " )  ', new Range('  a   a ', '  z   z  ', false, false)],
            ['(,z)',                            new Range(null, 'z', false, false)],
            ['(a,)',                            new Range('a', null, false, false)],
            ['[,z]',                            new Range(null, 'z', true, true)],
            ['[a,]',                            new Range('a', null, true, true)],
            ['(,)',                             new Range(null, null, false, false)],
            ['[ , ]',                           new Range(' ', ' ', true, true)],
            ['["",""]',                         new Range('', '', true, true)],
            ['[",",","]',                       new Range(',', ',', true, true)],
            ['["\\\\","\\\\"]',                 new Range('\\', '\\', true, true)],
            ['(\\\\,a)',                        new Range('\\', 'a', false, false)],
            ['((,z)',                           new Range('(', 'z', false, false)],
            ['([,z)',                           new Range('[', 'z', false, false)],
            ['(!,()',                           new Range('!', '(', false, false)],
            ['(!,[)',                           new Range('!', '[', false, false)],
            ['[a,a]',                           new Range('a', 'a', true, true)],

            // additional tests
            ['("a","bc\\\\\\""""]',             new Range('a', 'bc\\""', false, true)],
        ];
    }

    protected function valuesTo()
    {
        return [
            ['("a","bc\\\\\\"\\""]',            new Range('a', 'bc\\""', false, true)],
            ['["a","z")',                       ['a', 'z']]
        ];
    }
}
