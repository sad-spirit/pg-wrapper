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

use sad_spirit\pg_wrapper\converters\OID8Converter;
use sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Test for conversion of oid8 type added in Postgres 19
 *
 * The database-backed portion obviously requires Postgres 19
 *
 * @extends DatabaseBackedTypeConverterTestCase<OID8Converter>
 */
class OID8ConverterTest extends DatabaseBackedTypeConverterTestCase
{
    protected function setUp(): void
    {
        $this->converter = new OID8Converter();
    }

    public function typecast(): string
    {
        return 'oid8';
    }

    public function requiredDatabaseVersion(): ?string
    {
        return '19beta';
    }

    public static function valuesBoth(): array
    {
        return [
            [null,                   null],
            ['1',                    1],
            ['18446744073709551615', '18446744073709551615']
        ];
    }

    public static function valuesFrom(): array
    {
        return [
            ["\t1\n",                1],
            ['-1',                   new TypeConversionException()]
        ];
    }

    public static function valuesTo(): array
    {
        return [];
    }
}
