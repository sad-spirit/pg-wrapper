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

use sad_spirit\pg_wrapper\{
    converters\TidConverter,
    types\Tid,
    exceptions\InvalidArgumentException,
    exceptions\TypeConversionException
};

/**
 * Unit test for tid type converter
 *
 * @extends TypeConverterTestCase<TidConverter>
 */
class TidTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new TidConverter();
    }

    public static function valuesBoth(): array
    {
        return [
            ['(0,0)', new Tid(0, 0)],
            ['(1,2)', new Tid(1, 2)]
        ];
    }

    public static function valuesFrom(): array
    {
        return [
            [' ( 3 , 4 ) ', new Tid(3, 4)],
            ['666',         new TypeConversionException()],
            ['(5)',         new TypeConversionException()],
            ['(1,2,3)',     new TypeConversionException()],
            ['(1,2',        new TypeConversionException()],
            ['(-1,1)',      new InvalidArgumentException()],
            ['(1, -1)',     new InvalidArgumentException()]
        ];
    }

    public static function valuesTo(): array
    {
        return [
            ['(1,2)',                        ['tuple' => 2, 'block' => 1]],
            ['(1,2)',                        [1, 2]],
            ['(4294967280,1)',               ['4294967280', 1]],
            [new InvalidArgumentException(), ['-1', 1]],
            [new TypeConversionException(),  'a string'],
            [new InvalidArgumentException(), [1]],
            [new InvalidArgumentException(), [1, 2, 3]],
            [new InvalidArgumentException(), [-1, 2]],
            [new \TypeError(),               [1, 'foo']]
        ];
    }
}
