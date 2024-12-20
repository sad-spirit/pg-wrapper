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

use sad_spirit\pg_wrapper\converters\containers\HstoreConverter;
use sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Unit test for hstore type (from contrib) converter
 *
 * @extends TypeConverterTestCase<HstoreConverter>
 */
class HstoreTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new HstoreConverter();
    }

    public static function valuesBoth(): array
    {
        return [
            [null,                      null],
            ['',                        []],
            ['"0"=>"b"',                ['0' => 'b']],
            ['"a"=>"b"',                ['a' => 'b']],
            ['"a"=>"b", "b"=>"\\"a"',   ['a' => 'b', 'b' => '"a']],
            ['"a"=>NULL, "x"=>"123"',   ['a' => null, 'x' => '123']],
            ['"a"=>"null", "b"=>NULL',  ['a' => 'null', 'b' => null]]
        ];
    }

    public static function valuesFrom(): array
    {
        return [
            ['a=>b',                                  ['a' => 'b']],
            ['a   =>    b',                           ['a' => 'b']],
            ['a   =>    b, 4=>  "\\"x\\"y\\"z\\\\a"', ['a' => 'b', '4' => '"x"y"z\\a']],
            ['a=>b,',                                 ['a' => 'b']],
            [',a=>b',                                 [',a' => 'b']],
            ['a=>b=>c',                               ['a' => 'b=>c']],
            ["key\n=>value\n",                        ['key' => 'value']],
            ["key\t=>value\t",                        ['key' => 'value']],
            ["key\r=>value\r",                        ['key' => 'value']],
            ["key\v=>value\v",                        ['key' => 'value']],
            ["key\f=>value\f",                        ['key' => 'value']],
            ['a',                                     new TypeConversionException()],
            ['a,b',                                   new TypeConversionException()],
            ['a=>b,,,,,,',                            new TypeConversionException()],
            ['a=>',                                   new TypeConversionException()],
            [' =>b',                                  new TypeConversionException()]
        ];
    }

    public static function valuesTo(): array
    {
        return [
            [new TypeConversionException(),       1],
            ['"0"=>"1", "1"=>"a", "2"=>"xyz"',    [1, "a", 'xyz']],
            ['"0"=>"1", "1"=>"a", "test"=>"xyz"', [1, "a", 'test' => 'xyz']],
        ];
    }
}
