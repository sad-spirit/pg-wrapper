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

use sad_spirit\pg_wrapper\converters\geometric\LineConverter;
use sad_spirit\pg_wrapper\{
    types\Line,
    exceptions\InvalidArgumentException,
    exceptions\TypeConversionException
};

/**
 * Unit test for 'line' geometric type (9.4+) converter
 *
 * @extends TypeConverterTestCase<LineConverter>
 */
class LineTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new LineConverter();
    }

    public static function valuesBoth(): array
    {
        return [
            [null,             null],
            ['{1.2,3.4,5.6}',  new Line(1.2, 3.4, 5.6)]
        ];
    }

    public static function valuesFrom(): array
    {
        return [
            ['  {  1.2 , 3.4 ,    5.6}   ', new Line(1.2, 3.4, 5.6)],
            ['{ 1 , 2 , 3, 4}',             new TypeConversionException()],
            ['{1, 2}',                      new TypeConversionException()],
            ['{1,2,3}+',                    new TypeConversionException()],
            ['{1,2,3',                      new TypeConversionException()]
        ];
    }

    public static function valuesTo(): array
    {
        return [
            ['{1.2,3.4,5.6}',                ['C' => 5.6, 'A' => 1.2, 'B' => 3.4]],
            ['{1.2,3.4,5.6}',                [1.2, 3.4, 5.6]],
            [new TypeConversionException(),  'a line'],
            [new InvalidArgumentException(), [2, 4]]
        ];
    }
}
