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

use sad_spirit\pg_wrapper\converters\StringConverter;

/**
 * Unit test for string type(s) converter
 *
 * @extends TypeConverterTestCase<StringConverter>
 */
class StringTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new StringConverter();
    }

    public static function valuesBoth(): array
    {
        return [
            [null, null],
            ['', ''],
            ['1', '1'],
            ['2.324', '2.324'],
            ['text', 'text'],
        ];
    }

    public static function valuesFrom(): array
    {
        return [];
    }

    public static function valuesTo(): array
    {
        return [
            ['1', 1.0],
            ['-3', -3.00]
        ];
    }
}
