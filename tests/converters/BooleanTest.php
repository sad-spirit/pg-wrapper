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

use sad_spirit\pg_wrapper\converters\BooleanConverter;

/**
 * Unit test for boolean type converter
 *
 * @extends TypeConverterTestCase<BooleanConverter>
 */
class BooleanTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new BooleanConverter();
    }

    public static function valuesBoth(): array
    {
        return [
            [null, null],
            ['t', true],
            ['f', false],
        ];
    }

    public static function valuesFrom(): array
    {
        return [
            ['1', true],
            ['0', false],

            // whitespace handling
            ["f\f", false],
            ["\vf", false]
        ];
    }

    public static function valuesTo(): array
    {
        return [
            ['t', 'true'],
            ['t', 1],
            ['t', -1],
            ['t', '1'],
            ['t', '1.1'],
            ['t', '0.0'],
            ['t', 'string'],
            ['t', ['value']],
            ['t', [0]],

            ['f', 'false'],
            ['f', 0],
            ['f', '0'],
            ['f', []]
        ];
    }
}
