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

use sad_spirit\pg_wrapper\exceptions\InvalidArgumentException;

/**
 * Interface for classes that map OIDs to type names and check whether OIDs represent base or derived types
 *
 * @since 2.2.0
 */
interface TypeOIDMapper
{
    /**
     * Searches for an OID corresponding to the given type name in loaded type metadata
     *
     * @return int|numeric-string
     * @throws InvalidArgumentException
     */
    public function findOIDForTypeName(string $typeName, ?string $schemaName = null): int|string;

    /**
     * Searches for a type name corresponding to the given OID in loaded type metadata
     *
     * @param int|numeric-string $oid
     * @return array{string, string}
     * @throws InvalidArgumentException
     */
    public function findTypeNameForOID(int|string $oid): array;

    /**
     * Checks whether given OID corresponds to base type
     *
     * @param int|numeric-string $oid
     */
    public function isBaseTypeOID(int|string $oid): bool;

    /**
     * Checks whether given OID corresponds to array type
     *
     * `$baseTypeOid` will be set to OID of the array base type if the check succeeds
     *
     * @param int|numeric-string $oid
     * @param int|numeric-string|null $baseTypeOid
     *
     * @psalm-assert-if-true int|numeric-string $baseTypeOid
     */
    public function isArrayTypeOID(int|string $oid, int|string|null &$baseTypeOid = null): bool;

    /**
     * Checks whether given OID corresponds to composite type
     *
     * `$members` will contain a mapping `[fieldName => type OID, ...]` for fields of the composite type
     * if the check succeeds
     *
     * @param int|numeric-string $oid
     * @param array<string, int|numeric-string>|null $members
     *
     * @psalm-assert-if-true array<string, int|numeric-string> $members
     */
    public function isCompositeTypeOID(int|string $oid, array|null &$members = null): bool;

    /**
     * Checks whether given OID corresponds to domain type
     *
     * `$baseTypeOid` will be set to OID of the underlying data type if the check succeeds
     *
     * @param int|numeric-string $oid
     * @param int|numeric-string|null $baseTypeOid
     *
     * @psalm-assert-if-true int|numeric-string $baseTypeOid
     */
    public function isDomainTypeOID(int|string $oid, int|string|null &$baseTypeOid = null): bool;

    /**
     * Checks whether given OID corresponds to range type
     *
     * `$baseTypeOid` will be set to OID of the range base type if the check succeeds
     *
     * @param int|numeric-string $oid
     * @param int|numeric-string|null $baseTypeOid
     *
     * @psalm-assert-if-true int|numeric-string $baseTypeOid
     */
    public function isRangeTypeOID(int|string $oid, int|string|null &$baseTypeOid = null): bool;

    /**
     * Checks whether given OID corresponds to multirange type (available since Postgres 14)
     *
     * `$baseTypeOid` will be set to OID of the multirange base type if the check succeeds
     *
     * @param int|numeric-string $oid
     * @param int|numeric-string|null $baseTypeOid
     *
     * @psalm-assert-if-true int|numeric-string $baseTypeOid
     */
    public function isMultiRangeTypeOID(int|string $oid, int|string|null &$baseTypeOid = null): bool;
}
