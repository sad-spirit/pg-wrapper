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
    containers\ArrayConverter,
    StringConverter
};
use sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Unit test for an array of strings type converter
 *
 * @extends TypeConverterTestCase<ArrayConverter>
 */
class StringArrayTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new ArrayConverter(new StringConverter());
    }

    public static function valuesBoth(): array
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

    public static function valuesFrom(): array
    {
        return [
            ['{ {NULL} ,{  NULL, "string" }}  ', [[null], [null, 'string']]],
            ['{{NULL},{test,"\\"y"},{NULL,string}}', [[null], ['test', '"y'], [null, 'string']]],
            ["{{\na,b\r}\t,{\vc,d\f}}", [['a', 'b'], ['c', 'd']]]
        ];
    }

    public static function valuesTo(): array
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
