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

use sad_spirit\pg_wrapper\converters\containers\HstoreConverter;
use sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Unit test for hstore type (from contrib) converter
 */
class HstoreTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new HstoreConverter();
    }

    public function valuesBoth(): array
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

    public function valuesFrom(): array
    {
        return [
            ['a=>b',                                  ['a' => 'b']],
            ['a   =>    b',                           ['a' => 'b']],
            ['a   =>    b, 4=>  "\\"x\\"y\\"z\\\\a"', ['a' => 'b', '4' => '"x"y"z\\a']],
            ['a=>b,',                                 ['a' => 'b']],
            [',a=>b',                                 [',a' => 'b']],
            ['a=>b=>c',                               ['a' => 'b=>c']],
            ['a',                                     new TypeConversionException()],
            ['a,b',                                   new TypeConversionException()],
            ['a=>b,,,,,,',                            new TypeConversionException()],
            ['a=>',                                   new TypeConversionException()],
            [' =>b',                                  new TypeConversionException()]
        ];
    }

    public function valuesTo(): array
    {
        return [
            [new TypeConversionException(),       1],
            ['"0"=>"1", "1"=>"a", "2"=>"xyz"',    [1, "a", 'xyz']],
            ['"0"=>"1", "1"=>"a", "test"=>"xyz"', [1, "a", 'test' => 'xyz']],
        ];
    }
}
