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

namespace sad_spirit\pg_wrapper;

use sad_spirit\pg_wrapper\{
    converters\ConnectionAware,
    exceptions\InvalidArgumentException,
    exceptions\TypeConversionException
};

/**
 * Interface for classes that create type converters
 */
interface TypeConverterFactory extends ConnectionAware
{
    /**
     * Returns a converter specified by a given type
     *
     * The method should accept an instance of TypeConverter and update it from database connection
     * if it implements ConnectionAware interface.
     *
     * What else is accepted as type specification is up to implementation to decide. The obvious choices are
     *  - Type name
     *  - Composite type specification of the form ['field name' => 'type specification', ...]
     *
     * The method should throw an exception if a specific converter cannot be found for a given $type,
     * since that usually means user error.
     *
     * @param mixed $type Type specification
     * @return TypeConverter
     * @throws InvalidArgumentException
     */
    public function getConverterForTypeSpecification(mixed $type): TypeConverter;

    /**
     * Returns a converter for the type with the given OID
     *
     * OIDs (object identifiers) are used internally by PostgreSQL as primary keys for various system tables.
     * This method expects an OID that is a primary key of pg_type
     *
     * This is used mainly by Result to find converters for result columns
     *
     * Unlike getConverterForTypeSpecification() it should not throw an exception in case a converter is missing
     * for a specific base type, returning e.g. an instance of StubConverter instead. It may throw an exception
     * if the database does not have a type with the given OID.
     *
     * NB: this method accepts strings for the following reason: OIDs are _unsigned_ 32-bit integers and may be
     * out of range of PHP's _signed_ int type on 32-bit builds. For this same reason PHP itself does not
     * unconditionally convert OIDs to int, see
     * https://github.com/php/php-src/blob/2b32cafd880786b038767d5d7a56ed117a47ed51/ext/pgsql/pgsql.c#L64-L73
     *
     * @param int|numeric-string $oid
     * @return TypeConverter
     */
    public function getConverterForTypeOID(int|string $oid): TypeConverter;

    /**
     * Tries to return a converter based on type of $value
     *
     * Should throw TypeConversionException if it is not possible to find a proper converter, e.g.
     *  - input is ambiguous (PHP arrays can map to several DB types)
     *  - $value is an instance of class not explicitly known to Factory
     *
     * @param mixed $value
     * @return TypeConverter
     * @throws TypeConversionException
     */
    public function getConverterForPHPValue(mixed $value): TypeConverter;
}
