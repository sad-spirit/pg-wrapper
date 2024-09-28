<?php

/**
 * Converter of complex PostgreSQL types and an OO wrapper for PHP's pgsql extension
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-wrapper/master/LICENSE
 *
 * @package   sad_spirit\pg_wrapper
 * @copyright 2014-2024 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
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
