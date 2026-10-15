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

use PHPUnit\Framework\Attributes\DataProvider;
use sad_spirit\pg_wrapper\{
    Connection,
    TypeConverter,
    converters\StubTypeConverterFactory
};

/**
 * Adds test that passes string representations through DBMS
 *
 * @template T of TypeConverter
 * @extends TypeConverterTestCase<T>
 */
abstract class DatabaseBackedTypeConverterTestCase extends TypeConverterTestCase
{
    #[DataProvider('valuesBoth')]
    public function testCastUsingDatabase(?string $native, mixed $value): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this::markTestSkipped('Connection string is not configured');
        }
        $type       = $this->typecast();
        $connection = (new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING))
            ->setTypeConverterFactory(new StubTypeConverterFactory());
        if (
            (null !== $version = $this->requiredDatabaseVersion())
            && 0 > \version_compare(\pg_parameter_status($connection->getNative(), 'server_version'), $version)
        ) {
            $this::markTestSkipped('This test requires connection to Postgres version '. $version . '+');
        }
        $row        = $connection->executeParams(
            'select $1::' . $type . ' as native, $2::' . $type . ' as converted',
            [$native, $value],
            [$type, $this->converter]
        )
            ->current();
        $this::assertSame($row['native'], $row['converted']);
        $this::assertEquals($value, $this->converter->input($row['native']));
    }

    public function requiredDatabaseVersion(): ?string
    {
        return null;
    }

    abstract public function typecast(): string;
}
