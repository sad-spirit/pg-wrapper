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

namespace sad_spirit\pg_wrapper\converters;

use sad_spirit\pg_wrapper\{
    exceptions\RuntimeException,
    TypeConverterFactory,
    TypeConverter,
    Connection
};

/**
 * Returns StubConverter for any database type
 *
 * Should be used when type conversion is not needed, all values will be returned as strings
 * just as stock pgsql extension does.
 */
final class StubTypeConverterFactory implements TypeConverterFactory
{
    private readonly StubConverter $converter;
    private ?Connection $connection = null;

    public function __construct()
    {
        $this->converter = new StubConverter();
    }

    /**
     * {@inheritdoc}
     */
    public function getConverterForTypeSpecification(mixed $type): TypeConverter
    {
        if ($type instanceof TypeConverter) {
            if ($this->connection && $type instanceof ConnectionAware) {
                $type->setConnection($this->connection);
            }
            return $type;
        }
        return $this->converter;
    }

    /**
     * {@inheritdoc}
     */
    public function setConnection(Connection $connection): TypeConverterFactory
    {
        if ($this->connection && $connection !== $this->connection) {
            throw new RuntimeException("Connection already set");
        }
        $this->connection = $connection;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getConverterForPHPValue(mixed $value): TypeConverter
    {
        return $this->converter;
    }

    /**
     * {@inheritdoc}
     */
    public function getConverterForTypeOID(int|string $oid): TypeConverter
    {
        return $this->converter;
    }
}
