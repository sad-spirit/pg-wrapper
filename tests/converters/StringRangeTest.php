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
 *
 * @extends TypeConverterTestCase<RangeConverter>
 */
class StringRangeTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new RangeConverter(new StringConverter());
    }

    public static function valuesBoth(): array
    {
        return [
            ['empty',               Range::createEmpty()],
            ['("два","раз"]',       new Range('два', 'раз', false, true)]
        ];
    }

    public static function valuesFrom(): array
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

    public static function valuesTo(): array
    {
        return [
            ['("a","bc\\\\\\"\\""]',            new Range('a', 'bc\\""', false, true)],
            ['["a","z")',                       ['a', 'z']]
        ];
    }
}
