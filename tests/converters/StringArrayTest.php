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

use sad_spirit\pg_wrapper\converters\containers\ArrayConverter,
    sad_spirit\pg_wrapper\converters\StringConverter,
    sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Unit test for an array of strings type converter
 */
class StringArrayTest extends TypeConverterTestCase
{
    public function setUp()
    {
        $this->converter = new ArrayConverter(new StringConverter());
    }

    protected function valuesBoth()
    {
        return [
            [null, null],
            ['{}', []],
            ['{NULL,NULL}', [null, null]],
            ['{NULL,"test"}', [null, 'test']],
            ['{NULL,"te\\"s\\\\t"}', [null, 'te"s\\t']],
            ['{"abc","def"}', ['abc', 'def']],
            ['{{"abc","def"},{"g","h"}}', [['abc', 'def'], ['g', 'h']]],
            ['{"test"}', ['test']],
        ];
    }

    protected function valuesFrom()
    {
        return [
            ['{ {NULL} ,{  NULL, "string" }}  ', [[null], [null, 'string']]],
            ['{{NULL},{test,"\\"y"},{NULL,string}}', [[null], ['test', '"y'], [null, 'string']]],
        ];
    }

    protected function valuesTo()
    {
        return [
            [new TypeConversionException(), 1],
            [new TypeConversionException(), 'string'],
            [new TypeConversionException(), [['string'], null]],
            [new TypeConversionException(), [['ab', 'de'], []]],
            [new TypeConversionException(), [[], ['ab', 'de']]],
            // the result is accepted by Postgres, but probably shouldn't be
            // http://www.postgresql.org/message-id/E1VEETa-0007KM-8O@wrigleys.postgresql.org
            [new TypeConversionException(), [[null], [null, 'test']]],
            // empty sub-arrays
            [new TypeConversionException(), [[]]],
            [new TypeConversionException(), [[], []]]
        ];
    }
}
