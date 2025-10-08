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

use sad_spirit\pg_wrapper\Connection;
use sad_spirit\pg_wrapper\exceptions\{
    InvalidArgumentException,
    RuntimeException,
    ServerException
};

/**
 * Implementation of TypeOIDMapper loading type data from DB or from cache
 *
 * The methods were extracted from DefaultTypeConverterFactory class
 *
 * @since 2.2.0
 */
class CachedTypeOIDMapper implements ConnectionAware, TypeOIDMapper
{
    private const SOURCE_BUILTIN = 'built-in';
    private const SOURCE_CACHE   = 'cache';
    private const SOURCE_DB      = 'db';

    /**
     * Types list for current database, loaded from pg_catalog.pg_type
     *
     * The array is pre-populated with known builtin types of Postgres 18.
     * Only types with OIDs below 10000 are used since those OIDs are assigned manually
     * (see src/include/access/transam.h) and don't change between versions and
     * installations.
     *
     * While it may seem reasonable to omit pseudo-types from this list, some of these may
     * actually appear as types of result columns (e.g. generic "record") or even as table
     * columns (pg_catalog.pg_statistic has several "anyarray" columns). It's easier just
     * to keep all of them than to cherry-pick.
     *
     * @var array<string, array<string, int|numeric-string>> Mapping "type name" => ["schema name" => "type OID", ...],
     *                                        several schemas may contain types having the same name
     */
    private array $typeNames = [
        'bool'                         => ['pg_catalog' => 16],
        'bytea'                        => ['pg_catalog' => 17],
        'char'                         => ['pg_catalog' => 18],
        'name'                         => ['pg_catalog' => 19],
        'int8'                         => ['pg_catalog' => 20],
        'int2'                         => ['pg_catalog' => 21],
        'int2vector'                   => ['pg_catalog' => 22],
        'int4'                         => ['pg_catalog' => 23],
        'regproc'                      => ['pg_catalog' => 24],
        'text'                         => ['pg_catalog' => 25],
        'oid'                          => ['pg_catalog' => 26],
        'tid'                          => ['pg_catalog' => 27],
        'xid'                          => ['pg_catalog' => 28],
        'cid'                          => ['pg_catalog' => 29],
        'oidvector'                    => ['pg_catalog' => 30],
        'pg_ddl_command'               => ['pg_catalog' => 32],
        'pg_type'                      => ['pg_catalog' => 71],
        'pg_attribute'                 => ['pg_catalog' => 75],
        'pg_proc'                      => ['pg_catalog' => 81],
        'pg_class'                     => ['pg_catalog' => 83],
        'json'                         => ['pg_catalog' => 114],
        'xml'                          => ['pg_catalog' => 142],
        '_xml'                         => ['pg_catalog' => 143],
        'pg_node_tree'                 => ['pg_catalog' => 194],
        '_json'                        => ['pg_catalog' => 199],
        '_pg_type'                     => ['pg_catalog' => 210],
        'table_am_handler'             => ['pg_catalog' => 269],
        '_pg_attribute'                => ['pg_catalog' => 270],
        '_xid8'                        => ['pg_catalog' => 271],
        '_pg_proc'                     => ['pg_catalog' => 272],
        '_pg_class'                    => ['pg_catalog' => 273],
        'index_am_handler'             => ['pg_catalog' => 325],
        'point'                        => ['pg_catalog' => 600],
        'lseg'                         => ['pg_catalog' => 601],
        'path'                         => ['pg_catalog' => 602],
        'box'                          => ['pg_catalog' => 603],
        'polygon'                      => ['pg_catalog' => 604],
        'line'                         => ['pg_catalog' => 628],
        '_line'                        => ['pg_catalog' => 629],
        'cidr'                         => ['pg_catalog' => 650],
        '_cidr'                        => ['pg_catalog' => 651],
        'float4'                       => ['pg_catalog' => 700],
        'float8'                       => ['pg_catalog' => 701],
        'unknown'                      => ['pg_catalog' => 705],
        'circle'                       => ['pg_catalog' => 718],
        '_circle'                      => ['pg_catalog' => 719],
        'macaddr8'                     => ['pg_catalog' => 774],
        '_macaddr8'                    => ['pg_catalog' => 775],
        'money'                        => ['pg_catalog' => 790],
        '_money'                       => ['pg_catalog' => 791],
        'macaddr'                      => ['pg_catalog' => 829],
        'inet'                         => ['pg_catalog' => 869],
        '_bool'                        => ['pg_catalog' => 1000],
        '_bytea'                       => ['pg_catalog' => 1001],
        '_char'                        => ['pg_catalog' => 1002],
        '_name'                        => ['pg_catalog' => 1003],
        '_int2'                        => ['pg_catalog' => 1005],
        '_int2vector'                  => ['pg_catalog' => 1006],
        '_int4'                        => ['pg_catalog' => 1007],
        '_regproc'                     => ['pg_catalog' => 1008],
        '_text'                        => ['pg_catalog' => 1009],
        '_tid'                         => ['pg_catalog' => 1010],
        '_xid'                         => ['pg_catalog' => 1011],
        '_cid'                         => ['pg_catalog' => 1012],
        '_oidvector'                   => ['pg_catalog' => 1013],
        '_bpchar'                      => ['pg_catalog' => 1014],
        '_varchar'                     => ['pg_catalog' => 1015],
        '_int8'                        => ['pg_catalog' => 1016],
        '_point'                       => ['pg_catalog' => 1017],
        '_lseg'                        => ['pg_catalog' => 1018],
        '_path'                        => ['pg_catalog' => 1019],
        '_box'                         => ['pg_catalog' => 1020],
        '_float4'                      => ['pg_catalog' => 1021],
        '_float8'                      => ['pg_catalog' => 1022],
        '_polygon'                     => ['pg_catalog' => 1027],
        '_oid'                         => ['pg_catalog' => 1028],
        'aclitem'                      => ['pg_catalog' => 1033],
        '_aclitem'                     => ['pg_catalog' => 1034],
        '_macaddr'                     => ['pg_catalog' => 1040],
        '_inet'                        => ['pg_catalog' => 1041],
        'bpchar'                       => ['pg_catalog' => 1042],
        'varchar'                      => ['pg_catalog' => 1043],
        'date'                         => ['pg_catalog' => 1082],
        'time'                         => ['pg_catalog' => 1083],
        'timestamp'                    => ['pg_catalog' => 1114],
        '_timestamp'                   => ['pg_catalog' => 1115],
        '_date'                        => ['pg_catalog' => 1182],
        '_time'                        => ['pg_catalog' => 1183],
        'timestamptz'                  => ['pg_catalog' => 1184],
        '_timestamptz'                 => ['pg_catalog' => 1185],
        'interval'                     => ['pg_catalog' => 1186],
        '_interval'                    => ['pg_catalog' => 1187],
        '_numeric'                     => ['pg_catalog' => 1231],
        'pg_database'                  => ['pg_catalog' => 1248],
        '_cstring'                     => ['pg_catalog' => 1263],
        'timetz'                       => ['pg_catalog' => 1266],
        '_timetz'                      => ['pg_catalog' => 1270],
        'bit'                          => ['pg_catalog' => 1560],
        '_bit'                         => ['pg_catalog' => 1561],
        'varbit'                       => ['pg_catalog' => 1562],
        '_varbit'                      => ['pg_catalog' => 1563],
        'numeric'                      => ['pg_catalog' => 1700],
        'refcursor'                    => ['pg_catalog' => 1790],
        '_refcursor'                   => ['pg_catalog' => 2201],
        'regprocedure'                 => ['pg_catalog' => 2202],
        'regoper'                      => ['pg_catalog' => 2203],
        'regoperator'                  => ['pg_catalog' => 2204],
        'regclass'                     => ['pg_catalog' => 2205],
        'regtype'                      => ['pg_catalog' => 2206],
        '_regprocedure'                => ['pg_catalog' => 2207],
        '_regoper'                     => ['pg_catalog' => 2208],
        '_regoperator'                 => ['pg_catalog' => 2209],
        '_regclass'                    => ['pg_catalog' => 2210],
        '_regtype'                     => ['pg_catalog' => 2211],
        'record'                       => ['pg_catalog' => 2249],
        'cstring'                      => ['pg_catalog' => 2275],
        'any'                          => ['pg_catalog' => 2276],
        'anyarray'                     => ['pg_catalog' => 2277],
        'void'                         => ['pg_catalog' => 2278],
        'trigger'                      => ['pg_catalog' => 2279],
        'language_handler'             => ['pg_catalog' => 2280],
        'internal'                     => ['pg_catalog' => 2281],
        'anyelement'                   => ['pg_catalog' => 2283],
        '_record'                      => ['pg_catalog' => 2287],
        'anynonarray'                  => ['pg_catalog' => 2776],
        'pg_authid'                    => ['pg_catalog' => 2842],
        'pg_auth_members'              => ['pg_catalog' => 2843],
        '_txid_snapshot'               => ['pg_catalog' => 2949],
        'uuid'                         => ['pg_catalog' => 2950],
        '_uuid'                        => ['pg_catalog' => 2951],
        'txid_snapshot'                => ['pg_catalog' => 2970],
        'fdw_handler'                  => ['pg_catalog' => 3115],
        'pg_lsn'                       => ['pg_catalog' => 3220],
        '_pg_lsn'                      => ['pg_catalog' => 3221],
        'tsm_handler'                  => ['pg_catalog' => 3310],
        'pg_ndistinct'                 => ['pg_catalog' => 3361],
        'pg_dependencies'              => ['pg_catalog' => 3402],
        'anyenum'                      => ['pg_catalog' => 3500],
        'tsvector'                     => ['pg_catalog' => 3614],
        'tsquery'                      => ['pg_catalog' => 3615],
        'gtsvector'                    => ['pg_catalog' => 3642],
        '_tsvector'                    => ['pg_catalog' => 3643],
        '_gtsvector'                   => ['pg_catalog' => 3644],
        '_tsquery'                     => ['pg_catalog' => 3645],
        'regconfig'                    => ['pg_catalog' => 3734],
        '_regconfig'                   => ['pg_catalog' => 3735],
        'regdictionary'                => ['pg_catalog' => 3769],
        '_regdictionary'               => ['pg_catalog' => 3770],
        'jsonb'                        => ['pg_catalog' => 3802],
        '_jsonb'                       => ['pg_catalog' => 3807],
        'anyrange'                     => ['pg_catalog' => 3831],
        'event_trigger'                => ['pg_catalog' => 3838],
        'int4range'                    => ['pg_catalog' => 3904],
        '_int4range'                   => ['pg_catalog' => 3905],
        'numrange'                     => ['pg_catalog' => 3906],
        '_numrange'                    => ['pg_catalog' => 3907],
        'tsrange'                      => ['pg_catalog' => 3908],
        '_tsrange'                     => ['pg_catalog' => 3909],
        'tstzrange'                    => ['pg_catalog' => 3910],
        '_tstzrange'                   => ['pg_catalog' => 3911],
        'daterange'                    => ['pg_catalog' => 3912],
        '_daterange'                   => ['pg_catalog' => 3913],
        'int8range'                    => ['pg_catalog' => 3926],
        '_int8range'                   => ['pg_catalog' => 3927],
        'pg_shseclabel'                => ['pg_catalog' => 4066],
        'jsonpath'                     => ['pg_catalog' => 4072],
        '_jsonpath'                    => ['pg_catalog' => 4073],
        'regnamespace'                 => ['pg_catalog' => 4089],
        '_regnamespace'                => ['pg_catalog' => 4090],
        'regrole'                      => ['pg_catalog' => 4096],
        '_regrole'                     => ['pg_catalog' => 4097],
        'regcollation'                 => ['pg_catalog' => 4191],
        '_regcollation'                => ['pg_catalog' => 4192],
        'int4multirange'               => ['pg_catalog' => 4451],
        'nummultirange'                => ['pg_catalog' => 4532],
        'tsmultirange'                 => ['pg_catalog' => 4533],
        'tstzmultirange'               => ['pg_catalog' => 4534],
        'datemultirange'               => ['pg_catalog' => 4535],
        'int8multirange'               => ['pg_catalog' => 4536],
        'anymultirange'                => ['pg_catalog' => 4537],
        'anycompatiblemultirange'      => ['pg_catalog' => 4538],
        'pg_brin_bloom_summary'        => ['pg_catalog' => 4600],
        'pg_brin_minmax_multi_summary' => ['pg_catalog' => 4601],
        'pg_mcv_list'                  => ['pg_catalog' => 5017],
        'pg_snapshot'                  => ['pg_catalog' => 5038],
        '_pg_snapshot'                 => ['pg_catalog' => 5039],
        'xid8'                         => ['pg_catalog' => 5069],
        'anycompatible'                => ['pg_catalog' => 5077],
        'anycompatiblearray'           => ['pg_catalog' => 5078],
        'anycompatiblenonarray'        => ['pg_catalog' => 5079],
        'anycompatiblerange'           => ['pg_catalog' => 5080],
        'pg_subscription'              => ['pg_catalog' => 6101],
        '_int4multirange'              => ['pg_catalog' => 6150],
        '_nummultirange'               => ['pg_catalog' => 6151],
        '_tsmultirange'                => ['pg_catalog' => 6152],
        '_tstzmultirange'              => ['pg_catalog' => 6153],
        '_datemultirange'              => ['pg_catalog' => 6155],
        '_int8multirange'              => ['pg_catalog' => 6157]
    ];

    /**
     * Mapping of array type OIDs to their base type OIDs
     * @var array<int|numeric-string>
     */
    private array $arrayTypes = [
        1000 => 16,
        1001 => 17,
        1002 => 18,
        1003 => 19,
        1016 => 20,
        1005 => 21,
        1006 => 22,
        1007 => 23,
        1008 => 24,
        1009 => 25,
        1028 => 26,
        1010 => 27,
        1011 => 28,
        1012 => 29,
        1013 => 30,
        210  => 71,
        270  => 75,
        272  => 81,
        273  => 83,
        199  => 114,
        143  => 142,
        1017 => 600,
        1018 => 601,
        1019 => 602,
        1020 => 603,
        1027 => 604,
        629  => 628,
        651  => 650,
        1021 => 700,
        1022 => 701,
        719  => 718,
        775  => 774,
        791  => 790,
        1040 => 829,
        1041 => 869,
        1034 => 1033,
        1014 => 1042,
        1015 => 1043,
        1182 => 1082,
        1183 => 1083,
        1115 => 1114,
        1185 => 1184,
        1187 => 1186,
        1270 => 1266,
        1561 => 1560,
        1563 => 1562,
        1231 => 1700,
        2201 => 1790,
        2207 => 2202,
        2208 => 2203,
        2209 => 2204,
        2210 => 2205,
        2211 => 2206,
        2287 => 2249,
        1263 => 2275,
        2951 => 2950,
        2949 => 2970,
        3221 => 3220,
        3643 => 3614,
        3645 => 3615,
        3644 => 3642,
        3735 => 3734,
        3770 => 3769,
        3807 => 3802,
        3905 => 3904,
        3907 => 3906,
        3909 => 3908,
        3911 => 3910,
        3913 => 3912,
        3927 => 3926,
        4073 => 4072,
        4090 => 4089,
        4097 => 4096,
        4192 => 4191,
        6150 => 4451,
        6151 => 4532,
        6152 => 4533,
        6153 => 4534,
        6155 => 4535,
        6157 => 4536,
        5039 => 5038,
        271  => 5069
    ];

    /**
     * Mapping of composite type OIDs to relation OIDs or type structure
     *
     * If data for composite type was not yet loaded, its key contains relation OID,
     * afterwards it contains type specification of the form
     * ['field name' => 'field type OID', ...]
     *
     * @var array<int|numeric-string|array<string, int|numeric-string>>
     */
    private array $compositeTypes = [
        71   => 1247,
        75   => 1249,
        81   => 1255,
        83   => 1259,
        1248 => 1262,
        2842 => 1260,
        2843 => 1261,
        4066 => 3592,
        6101 => 6100
    ];

    /**
     * Mapping of domain type OIDs to their base type OIDs
     * @var array<int|numeric-string>
     */
    private array $domainTypes = [];

    /**
     * Mapping of range type OIDs to their base type OIDs
     * @var array<int|numeric-string>
     */
    private array $rangeTypes = [
        3904 => 23,
        3906 => 1700,
        3908 => 1114,
        3910 => 1184,
        3912 => 1082,
        3926 => 20
    ];

    /**
     * Mapping of multirange type OIDs to their base type OIDs (Postgres 14+)
     * @var array<int|numeric-string>
     */
    private array $multiRangeTypes = [
        4451 => 23,
        4532 => 1700,
        4533 => 1114,
        4534 => 1184,
        4535 => 1082,
        4536 => 20
    ];

    /** Source of $dbTypes data, one of SOURCE_* constants */
    private string $dbTypesSource = self::SOURCE_BUILTIN;

    /** Whether to cache composite types' structure */
    private bool $compositeTypesCaching = true;

    /**
     * Mapping 'type OID' => ['schema name', 'type name']
     *
     * This is built based on $typeNames, but not saved to cache
     *
     * @var array<array{string, string}>
     */
    private array $oidMap = [];
    private ?Connection $connection = null;

    public function __construct(?Connection $connection = null)
    {
        if (null !== $connection) {
            $this->setConnection($connection);
        }
        $this->buildOIDMap();
    }

    public function setConnection(Connection $connection): void
    {
        if ($this->connection && $connection !== $this->connection) {
            throw new RuntimeException("Connection already set");
        }
        $this->connection = $connection;
    }

    /**
     * Sets whether composite types' structure is cached
     *
     * Composite types' (both freestanding and representing table rows) internal structure can change
     * when columns (attributes) are added / removed / changed. If the cached list of columns is used to convert
     * the composite value with different columns the conversion will obviously fail.
     *
     * This should be set to false if
     *  - composite types are used in the application
     *  - changes to those types are expected
     * Otherwise it can be left at the default (true)
     *
     * @return $this
     */
    final public function setCompositeTypesCaching(bool $caching): self
    {
        $this->compositeTypesCaching = $caching;

        return $this;
    }

    /**
     * Returns whether composite types' structure is cached
     */
    final public function getCompositeTypesCaching(): bool
    {
        return $this->compositeTypesCaching;
    }

    public function isArrayTypeOID(int|string $oid, int|string|null &$baseTypeOid = null): bool
    {
        if (!isset($this->arrayTypes[$oid])) {
            return false;
        } else {
            $baseTypeOid = $this->arrayTypes[$oid];
            return true;
        }
    }

    public function isRangeTypeOID(int|string $oid, int|string|null &$baseTypeOid = null): bool
    {
        if (!isset($this->rangeTypes[$oid])) {
            return false;
        } else {
            $baseTypeOid = $this->rangeTypes[$oid];
            return true;
        }
    }

    public function isMultiRangeTypeOID(int|string $oid, int|string|null &$baseTypeOid = null): bool
    {
        if (!isset($this->multiRangeTypes[$oid])) {
            return false;
        } else {
            $baseTypeOid = $this->multiRangeTypes[$oid];
            return true;
        }
    }

    public function isDomainTypeOID(int|string $oid, int|string|null &$baseTypeOid = null): bool
    {
        if (!isset($this->domainTypes[$oid])) {
            return false;
        } else {
            $baseTypeOid = $this->domainTypes[$oid];
            return true;
        }
    }

    public function isCompositeTypeOID(int|string $oid, array|null &$members = null): bool
    {
        if (!isset($this->compositeTypes[$oid])) {
            return false;
        } else {
            $members = $this->getCompositeTypeMembers($oid);
            return true;
        }
    }

    public function isBaseTypeOID(int|string $oid): bool
    {
        return !isset($this->arrayTypes[$oid])
               && !isset($this->rangeTypes[$oid])
               && !isset($this->multiRangeTypes[$oid])
               && !isset($this->compositeTypes[$oid])
               && !isset($this->domainTypes[$oid]);
    }

    public function findTypeNameForOID(int|string $oid): array
    {
        if (
            !$this->checkTypesArrayWithPossibleReload(
                fn(): bool => isset($this->oidMap[$oid]),
                'Database connection required'
            )
        ) {
            throw new InvalidArgumentException(\sprintf('Could not find type information for OID %d', $oid));
        }

        return $this->oidMap[$oid];
    }

    public function findOIDForTypeName(string $typeName, ?string $schemaName = null): int|string
    {
        if (
            !$this->checkTypesArrayWithPossibleReload(
                fn(): bool => isset($this->typeNames[$typeName])
                    && (null === $schemaName || isset($this->typeNames[$typeName][$schemaName])),
                \sprintf(
                    "Database connection required to process type name %s",
                    InvalidArgumentException::formatQualifiedName($typeName, $schemaName)
                )
            )
        ) {
            throw new InvalidArgumentException(\sprintf(
                'Type %s does not exist in the database',
                InvalidArgumentException::formatQualifiedName($typeName, $schemaName)
            ));
        }

        if ($schemaName) {
            return $this->typeNames[$typeName][$schemaName];

        } elseif (1 === \count($this->typeNames[$typeName])) {
            return \reset($this->typeNames[$typeName]);

        } else {
            throw new InvalidArgumentException(\sprintf(
                'Types named "%s" found in schemas: %s. Qualified name required.',
                $typeName,
                \implode(', ', \array_keys($this->typeNames[$typeName]))
            ));
        }
    }


    /**
     * Checks for presence of keys in $dbTypes array using provided condition
     *
     * If the keys are not present, may reload the array from cache / database, erroring if the connection
     * needed for that is not available
     *
     * @param callable $condition                 Should return true if required keys are present, false otherwise
     * @param string   $connectionRequiredMessage Message for the exception thrown if required connection is missing
     * @throws RuntimeException
     */
    private function checkTypesArrayWithPossibleReload(callable $condition, string $connectionRequiredMessage): bool
    {
        if ($condition()) {
            return true;

        } elseif (!$this->connection) {
            throw new RuntimeException($connectionRequiredMessage);

        } elseif (self::SOURCE_BUILTIN === $this->dbTypesSource) {
            $this->loadTypes();
            if ($condition()) {
                return true;
            } elseif (self::SOURCE_DB === $this->dbTypesSource) {
                return false;
            }
        }

        $this->loadTypes(true);
        return (bool)$condition();
    }


    /**
     * Populates the types list from pg_catalog.pg_type table
     *
     * @param bool $force Force loading from database even if cached list is available
     * @throws ServerException
     */
    private function loadTypes(bool $force = false): void
    {
        if (null === $this->connection) {
            throw new RuntimeException(__METHOD__ . '(): Database connection required');
        }

        $cacheItem = null;
        if ($cache = $this->connection->getMetadataCache()) {
            try {
                $cacheItem = $cache->getItem($this->connection->getConnectionId() . '-types');
            } catch (\Psr\Cache\InvalidArgumentException) {
            }
        }

        if (!$force && null !== $cacheItem && $cacheItem->isHit()) {
            $cached                = $cacheItem->get();
            $this->dbTypesSource   = self::SOURCE_CACHE;
            $this->arrayTypes      = $cached['array'] ?? [];
            $this->compositeTypes  = $cached['composite'] ?? [];
            $this->domainTypes     = $cached['domain'] ?? [];
            $this->rangeTypes      = $cached['range'] ?? [];
            $this->multiRangeTypes = $cached['multirange'] ?? [];
            $this->typeNames       = $cached['names'] ?? [];

        } else {
            $this->arrayTypes      = [];
            $this->compositeTypes  = [];
            $this->domainTypes     = [];
            $this->rangeTypes      = [];
            $this->multiRangeTypes = [];
            $this->typeNames       = [];
            $sql = <<<SQL
select t.oid, nspname, typname, typarray, typrelid, typbasetype
from pg_catalog.pg_type as t, pg_catalog.pg_namespace as s
where t.typnamespace = s.oid
order by 1
SQL;
            if (!($res = @\pg_query($this->connection->getNative(), $sql))) {
                throw ServerException::fromConnection($this->connection);
            }
            $converter = new IntegerConverter();
            while ($row = \pg_fetch_assoc($res)) {
                if (!isset($this->typeNames[$row['typname']])) {
                    $this->typeNames[$row['typname']] = [$row['nspname'] => $converter->input($row['oid'])];
                } else {
                    $this->typeNames[$row['typname']][$row['nspname']] = $converter->input($row['oid']);
                }
                if ('0' !== $row['typarray']) {
                    $this->arrayTypes[$row['typarray']] = $converter->input($row['oid']);
                }
                if ('0' !== $row['typrelid']) {
                    $this->compositeTypes[$row['oid']] = $converter->input($row['typrelid']);
                }
                if ('0' !== $row['typbasetype']) {
                    $this->domainTypes[$row['oid']] = $converter->input($row['typbasetype']);
                }
            }
            \pg_free_result($res);

            if ($this->getCompositeTypesCaching()) {
                // Preload columns for freestanding composite types: they are far more likely to appear
                // in result sets than those linked to tables.
                $sql = <<<SQL
select reltype, attname, atttypid
from pg_catalog.pg_attribute as a, pg_catalog.pg_class as c
where a.attrelid = c.oid and
      c.relkind  = 'c' and
      a.attnum > 0
order by attrelid, attnum
SQL;
                if (!($res = @\pg_query($this->connection->getNative(), $sql))) {
                    throw ServerException::fromConnection($this->connection);
                }
                while ($row = \pg_fetch_assoc($res)) {
                    $relTypeId = $converter->input($row['reltype']);
                    if (!isset($this->compositeTypes[$relTypeId]) || !\is_array($this->compositeTypes[$relTypeId])) {
                        $this->compositeTypes[$relTypeId] = [];
                    }
                    $this->compositeTypes[$relTypeId][$row['attname']] = $converter->input($row['atttypid']);
                }
                \pg_free_result($res);
            }

            if (!($res = @\pg_query($this->connection->getNative(), "select * from pg_range"))) {
                throw ServerException::fromConnection($this->connection);
            }
            while ($row = \pg_fetch_assoc($res)) {
                if (\array_key_exists('rngmultitypid', $row)) {
                    $this->multiRangeTypes[$row['rngmultitypid']] = $converter->input($row['rngsubtype']);
                }
                $this->rangeTypes[$row['rngtypid']] = $converter->input($row['rngsubtype']);
            }
            \pg_free_result($res);

            if ($cache && $cacheItem) {
                $cache->save($cacheItem->set([
                    'array'      => $this->arrayTypes,
                    'composite'  => $this->compositeTypes,
                    'domain'     => $this->domainTypes,
                    'range'      => $this->rangeTypes,
                    'multirange' => $this->multiRangeTypes,
                    'names'      => $this->typeNames
                ]));
            }

            $this->dbTypesSource = self::SOURCE_DB;
        }

        $this->buildOIDMap();
    }


    /**
     * Returns a converter for a database-specific composite type
     *
     * @param int|numeric-string $oid
     * @return array<string, int|numeric-string>
     * @throws ServerException
     * @throws RuntimeException
     */
    private function getCompositeTypeMembers(int|string $oid): array
    {
        if (null === $this->connection) {
            throw new RuntimeException('Database connection required for getting composite types data');
        }

        if (!\is_array($this->compositeTypes[$oid])) {
            $cacheItem = null;
            if (($cache = $this->connection->getMetadataCache()) && $this->getCompositeTypesCaching()) {
                try {
                    $cacheItem = $cache->getItem(
                        \sprintf('%s-composite-%s', $this->connection->getConnectionId(), $oid)
                    );
                } catch (\Psr\Cache\InvalidArgumentException) {
                }
            }

            if (null !== $cacheItem && $cacheItem->isHit()) {
                $this->compositeTypes[$oid] = $cacheItem->get();

            } else {
                $sql = <<<SQL
select attname, atttypid
from pg_catalog.pg_attribute
where attrelid = $1 and
      attnum > 0
order by attnum
SQL;
                if (
                    !($res = @\pg_query_params(
                        $this->connection->getNative(),
                        $sql,
                        [$this->compositeTypes[$oid]]
                    ))
                ) {
                    throw ServerException::fromConnection($this->connection);
                }
                $this->compositeTypes[$oid] = [];
                $converter = new IntegerConverter();
                while ($row = \pg_fetch_assoc($res)) {
                    $this->compositeTypes[$oid][$row['attname']] = $converter->input($row['atttypid']);
                }
                \pg_free_result($res);

                if ($cache && $cacheItem) {
                    $cache->save($cacheItem->set($this->compositeTypes[$oid]));
                }
            }
        }

        return $this->compositeTypes[$oid];
    }

    /**
     * Builds mapping ['type OID' => ['schema name', 'type name']] using information from $dbTypes
     */
    private function buildOIDMap(): void
    {
        $this->oidMap = [];
        foreach ($this->typeNames as $typeName => $schemas) {
            foreach ($schemas as $schemaName => $oid) {
                $this->oidMap[$oid] = [$schemaName, $typeName];
            }
        }
    }
}
