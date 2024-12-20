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
    containers\HstoreConverter,
    containers\ArrayConverter
};

/**
 * Unit test for a combination of array and hstore type converters
 *
 * @extends TypeConverterTestCase<ArrayConverter>
 */
class HstoreArrayTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new ArrayConverter(new HstoreConverter());
    }

    public static function valuesBoth(): array
    {
        return [
            [null, null],
            ['{"\"a\"=>\"b\"","\"c\"=>\"d\", \"e\"=>\"f\""}', [['a' => 'b'], ['c' => 'd', 'e' => 'f']]],
            ['{"\"g\"=>\"h\"",NULL}', [['g' => 'h'], null]],
            [
                '{{"","\"a\"=>\"b\""},{"\"c\"=>\"d\"",NULL}}',
                [[[], ['a' => 'b']], [['c' => 'd'], null]]
            ]
        ];
    }

    public static function valuesFrom(): array
    {
        return [];
    }

    public static function valuesTo(): array
    {
        return [];
    }
}
