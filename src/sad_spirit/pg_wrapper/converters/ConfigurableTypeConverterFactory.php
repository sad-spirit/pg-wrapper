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
    TypeConverter,
    TypeConverterFactory,
    exceptions\InvalidArgumentException
};

/**
 * Interface for type converter factories that can be configured with additional types and converters
 *
 * This interface should be used for type hinting when an instance of DefaultTypeConverterFactory is implied
 *
 * @since 3.1.0
 */
interface ConfigurableTypeConverterFactory extends TypeConverterFactory, TypeOIDMapperAware
{
    /**
     * Registers a converter for a named type
     *
     * If a converter is requested for the given type name through {@see getConverterForQualifiedName}, then
     * a `TypeConverter` implementation created using the given `$converter` is returned.
     *
     * `$converter` can be either of
     * - `TypeConverter` instance, a clone of that should be returned;
     * - a callable returning an instance of `TypeConverter`;
     * - class name of a `TypeConverter` implementation.
     *
     * @param class-string<TypeConverter>|callable|TypeConverter $converter
     * @param string|string[]                                    $type
     */
    public function registerConverter(
        callable|TypeConverter|string $converter,
        array|string $type,
        string $schema = 'pg_catalog'
    ): void;

    /**
     * Registers a mapping between PHP class and database type name
     *
     * If an instance of the given class will later be provided to {@see getConverterForPHPValue()},
     * that method will return a converter for the given database type
     *
     * @param class-string $className
     */
    public function registerClassMapping(string $className, string $type, string $schema = 'pg_catalog'): void;

    /**
     * Returns type converter for separately supplied type and schema names
     *
     * An exception will be thrown if a converter for a base type is requested, and it was not registered
     * via {@see registerConverter()}
     *
     * @throws InvalidArgumentException
     */
    public function getConverterForQualifiedName(string $typeName, ?string $schemaName = null): TypeConverter;
}
