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

use sad_spirit\pg_wrapper\converters\geometric\PointConverter;
use sad_spirit\pg_wrapper\{
    types\Point,
    exceptions\TypeConversionException,
    exceptions\InvalidArgumentException
};

/**
 * Unit test for 'point' geometric type converter
 *
 * @extends TypeConverterTestCase<PointConverter>
 */
class PointTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new PointConverter();
    }

    public static function valuesBoth(): array
    {
        return [
            [null,        null],
            ['(0,0)',     new Point(0, 0)],
            ['(1,2)',     new Point(1, 2)],
            ['(0.6,0.3)', new Point(0.6, 0.3)],
        ];
    }

    public static function valuesFrom(): array
    {
        return [
            ['0,3.2',       new Point(0, 3.2)],
            [' 0.1 , 3.2 ', new Point(0.1, 3.2)],
            ['(0,0',        new TypeConversionException()],
            ['0,0)',        new TypeConversionException()],
            ['[0,0]',       new TypeConversionException()]
        ];
    }

    public static function valuesTo(): array
    {
        return [
            ['(1,2)',                       ['y' => 2, 'x' => 1]],
            ['(3,4)',                       [3, 4]],
            [new TypeConversionException(), 1],
            [new TypeConversionException(), 'point'],
            [new InvalidArgumentException(), [1]],
            [new InvalidArgumentException(), [1, 1, 1]],
            [new InvalidArgumentException(), []],
            [new \TypeError(),               [2, 'string']],
        ];
    }
}
