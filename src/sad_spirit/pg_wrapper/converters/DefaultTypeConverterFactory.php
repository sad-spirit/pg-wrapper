<?php
/**
 * Wrapper for PHP's pgsql extension providing conversion of complex DB types
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-wrapper/master/LICENSE
 *
 * @package   sad_spirit\pg_wrapper
 * @copyright 2014-2017 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\converters;

use sad_spirit\pg_wrapper\{
    TypeConverterFactory,
    TypeConverter,
    Connection,
    exceptions\InvalidArgumentException,
    exceptions\InvalidQueryException,
    exceptions\RuntimeException
};

/**
 * Creates type converters for database type based on specific DB metadata
 */
class DefaultTypeConverterFactory implements TypeConverterFactory
{
    /**
     * Mapping from one-word SQL standard types to native types
     */
    private const SIMPLE_ALIASES = [
        'int'       => 'int4',
        'integer'   => 'int4',
        'smallint'  => 'int2',
        'bigint'    => 'int8',
        'real'      => 'float4',
        'float'     => 'float8',
        'decimal'   => 'numeric',
        'dec'       => 'numeric',
        'boolean'   => 'bool',
        'character' => 'text',
        'nchar'     => 'text'
    ];

    /**
     * DB connection object
     * @var Connection
     */
    private $connection;

    /**
     * Types list for current database, loaded from pg_catalog.pg_type
     *
     * 'composite': array('type oid' => 'relation oid'), for composite types
     * 'array': array('array type oid' => 'base type oid'), for arrays
     * 'range': array('range type oid' => 'base type oid'), for ranges
     * 'names': array('type name' => array('schema name' => 'type oid', ...))
     *
     * @var array
     */
    private $dbTypes = [
        'composite' => [],
        'array'     => [],
        'range'     => [],
        'names'     => []
    ];

    /**
     * Mapping type oid => array('schema name', 'type name')
     *
     * This is built based on _dbTypes['names'], but not saved to cache
     *
     * @var array
     */
    private $oidMap = [];

    /**
     * Mapping of known base types to converter class names
     * @var array
     */
    private $types = [];

    /**
     * Converter instances
     * @var array
     */
    private $converters = [];

    /**
     * Whether to cache composite types' structure
     * @var bool
     */
    private $compositeTypesCaching = true;

    /**
     * Mapping "type name as string" => array("type name", "schema name", "is array")
     * @var array
     */
    private $parsedNames = [];

    /**
     * Constructor, registers converters for built-in types
     */
    public function __construct()
    {
        if (extension_loaded('mbstring') && (2 & ini_get('mbstring.func_overload'))) {
            throw new RuntimeException(
                'Multibyte function overloading must be disabled for correct parsing of database values'
            );
        }

        $this->registerConverter(BooleanConverter::class, 'bool');
        $this->registerConverter(ByteaConverter::class, 'bytea');
        $this->registerConverter(
            IntegerConverter::class,
            ['oid', 'cid', 'xid', 'int2', 'int4', 'int8']
        );
        $this->registerConverter(NumericConverter::class, 'numeric');
        $this->registerConverter(
            FloatConverter::class,
            ['float4', 'float8', 'money']
        );
        $this->registerConverter(datetime\DateConverter::class, 'date');
        $this->registerConverter(datetime\TimeConverter::class, 'time');
        $this->registerConverter(datetime\TimeTzConverter::class, 'timetz');
        $this->registerConverter(datetime\TimeStampConverter::class, 'timestamp');
        $this->registerConverter(
            datetime\TimeStampTzConverter::class,
            ['timestamptz', 'abstime']
        );
        $this->registerConverter(
            datetime\IntervalConverter::class,
            ['interval', 'reltime']
        );
        $this->registerConverter(
            StringConverter::class,
            ['cstring', 'text', 'char', 'varchar', 'bpchar', 'name']
        );
        $this->registerConverter(TidConverter::class, 'tid');

        $this->registerConverter(geometric\PointConverter::class, 'point');
        $this->registerConverter(geometric\CircleConverter::class, 'circle');
        $this->registerConverter(geometric\LineConverter::class, 'line');
        $this->registerConverter(geometric\LSegConverter::class, 'lseg');
        $this->registerConverter(geometric\BoxConverter::class, 'box');
        $this->registerConverter(geometric\PathConverter::class, 'path');
        $this->registerConverter(geometric\PolygonConverter::class, 'polygon');

        $this->registerConverter(containers\HstoreConverter::class, 'hstore', 'public');

        $this->registerConverter(
            JSONConverter::class,
            ['json', 'jsonb']
        );

        $this->registerConverter(function () {
            return new containers\RangeConverter(new IntegerConverter());
        }, ['int4range', 'int8range']);
        $this->registerConverter(function () {
            return new containers\RangeConverter(new NumericConverter());
        }, 'numrange');
        $this->registerConverter(function () {
            return new containers\RangeConverter(new datetime\DateConverter());
        }, 'daterange');
        $this->registerConverter(function () {
            return new containers\RangeConverter(new datetime\TimeStampConverter());
        }, 'tsrange');
        $this->registerConverter(function () {
            return new containers\RangeConverter(new datetime\TimeStampTzConverter());
        }, 'tstzrange');
    }

    /**
     * Registers a converter for a known named type
     *
     * @param string|callable|TypeConverter $converter
     * @param string|array                  $type
     * @param string                        $schema
     * @throws InvalidArgumentException
     */
    public function registerConverter($converter, $type, $schema = 'pg_catalog')
    {
        if (!is_string($converter) && !is_callable($converter) && !($converter instanceof TypeConverter)) {
            throw new InvalidArgumentException(sprintf(
                '%s() expects a class name, a closure or an instance of TypeConverter, %s given',
                __METHOD__,
                is_object($converter) ? 'object(' . get_class($converter) . ')' : gettype($converter)
            ));
        }
        foreach ((array)$type as $typeName) {
            if (isset($this->converters[$typeName])) {
                unset($this->converters[$typeName][$schema]);
            }
            if (!isset($this->types[$typeName])) {
                $this->types[$typeName] = [$schema => $converter];
            } else {
                $this->types[$typeName][$schema] = $converter;
            }
        }
    }

    /**
     * Sets database connection details for this object
     *
     * Database connection is used for reading the types data from the
     * catalog, connection id is used for storing that data in cache.
     *
     * @param Connection $connection
     * @return $this
     */
    public function setConnection(Connection $connection): TypeConverterFactory
    {
        if (!empty($this->connection)) {
            // prevent reusing old converters with new connection
            $this->converters = [];
        }

        $this->connection = $connection;
        foreach ($this->converters as $converter) {
            $this->updateConnection($converter);
        }

        $this->loadTypes();

        return $this;
    }

    /**
     * Updates connection data for ConnectionAware converter
     *
     * @param TypeConverter $converter
     */
    private function updateConnection(TypeConverter $converter)
    {
        if ($this->connection && $converter instanceof ConnectionAware) {
            $converter->setConnectionResource($this->connection->getResource());
        }
    }

    /**
     * Sets whether composite types' structure is cached
     *
     * Composite types' (both free-standing and representing table rows) internal structure can change
     * when columns (attributes) are added / removed / changed. If the cached list of columns is used to convert
     * the composite value with different columns the conversion will obviously fail.
     *
     * This should be set to false if
     *  - composite types are used in the application
     *  - changes to those types are expected
     * Otherwise it can be left at the default (true)
     *
     * @param bool $caching
     * @return $this
     */
    public function setCompositeTypesCaching($caching)
    {
        $this->compositeTypesCaching = (bool)$caching;

        return $this;
    }

    /**
     * Returns whether composite types' structure is cached
     *
     * @return bool
     */
    public function getCompositeTypesCaching()
    {
        return $this->compositeTypesCaching;
    }

    /**
     * Returns a converter for a given database type
     *
     * $type can be either of
     *  - type oid (integer)
     *  - type name (string), either simple or schema-qualified,
     *    'foo[]' is treated as an array of base type 'foo'
     *  - array('field' => 'type', ...) for composite types
     *  - TypeConverter instance. If it implements ConnectionAware, then
     *    it will receive current connection resource
     *
     * Converters for type names registered with registerConverter() will
     * be returned even without database connection. Getting Converters for
     * type oids and database-specific names (e.g. composite types) require
     * a connection.
     *
     * If no converter was registered for a (base) type, the outcome depends
     * on the parameter provided:
     *  - if type name was used, an exception will be thrown
     *  - if type oid was used, a fallback converter (StubConverter) will
     *    be returned
     *
     * This allows unknown types in ResultSet to be returned as strings, as
     * pgsql extension itself does, while at the same time prevents errors
     * when manually requesting converters for a type.
     *
     * @param mixed $type
     * @return TypeConverter
     * @throws InvalidArgumentException
     */
    public function getConverter($type): TypeConverter
    {
        if ($type instanceof TypeConverter) {
            $this->updateConnection($type);
            return $type;

        } elseif (is_scalar($type)) {
            if (ctype_digit((string)$type)) {
                // type oid given
                return $this->getConverterForTypeOid($type);
            } else {
                // type name given
                return $this->getConverterForTypeName($type);
            }

        } elseif (is_array($type)) {
            // type specification for composite type
            $types = [];
            foreach ($type as $k => $v) {
                $types[$k] = $this->getConverter($v);
            }
            return new containers\CompositeConverter($types);
        }

        throw new InvalidArgumentException(sprintf(
            '%s expects either of: type oid, type name, composite type array,'
            . ' instance of TypeConverter. %s given',
            __METHOD__,
            is_object($type) ? 'object(' . get_class($type) . ')' : gettype($type)
        ));
    }

    /**
     * Checks whether given oid corresponds to array type
     *
     * $baseTypeOid will be set to oid of the array base type
     *
     * @param int      $oid
     * @param int|null $baseTypeOid
     * @return bool
     */
    protected function isArrayTypeOid($oid, &$baseTypeOid = null)
    {
        if (!isset($this->dbTypes['array'][$oid])) {
            return false;
        } else {
            $baseTypeOid = $this->dbTypes['array'][$oid];
            return true;
        }
    }

    /**
     * Checks whether given oid corresponds to range type
     *
     * $baseTypeOid will be set to oid of the range base type
     *
     * @param int      $oid
     * @param int|null $baseTypeOid
     * @return bool
     */
    protected function isRangeTypeOid($oid, &$baseTypeOid = null)
    {
        if (!isset($this->dbTypes['range'][$oid])) {
            return false;
        } else {
            $baseTypeOid = $this->dbTypes['range'][$oid];
            return true;
        }
    }

    /**
     * Checks whether given oid corresponds to composite type
     *
     * @param int $oid
     * @return bool
     */
    protected function isCompositeTypeOid($oid)
    {
        return isset($this->dbTypes['composite'][$oid]);
    }

    /**
     * Checks whether given oid corresponds to base type
     *
     * @param int $oid
     * @return bool
     */
    protected function isBaseTypeOid($oid)
    {
        return !isset($this->dbTypes['array'][$oid])
               && !isset($this->dbTypes['range'][$oid])
               && !isset($this->dbTypes['composite'][$oid]);
    }


    /**
     * Returns a converter for a database type identified by oid
     *
     * @param int $oid
     * @return TypeConverter
     * @throws InvalidArgumentException
     */
    private function getConverterForTypeOid($oid)
    {
        if ($this->isArrayTypeOid($oid, $baseTypeOid)) {
            return new containers\ArrayConverter(
                $this->getConverterForTypeOid($baseTypeOid)
            );

        } elseif ($this->isRangeTypeOid($oid, $baseTypeOid)) {
            return new containers\RangeConverter(
                $this->getConverterForTypeOid($baseTypeOid)
            );

        } elseif ($this->isCompositeTypeOid($oid)) {
            return $this->getConverterForCompositeTypeOid($oid);
        }

        list($schemaName, $typeName) = $this->findTypeNameForOid($oid, __METHOD__);

        try {
            return $this->getConverterForQualifiedName($typeName, $schemaName);
        } catch (InvalidArgumentException $e) {
            return new StubConverter();
        }
    }

    /**
     * Searches for a type name corresponding to the given oid in loaded type metadata
     *
     * @param int    $oid
     * @param string $method Used in Exception messages only
     * @return array
     * @throws InvalidArgumentException
     */
    protected function findTypeNameForOid($oid, $method)
    {
        if (!$this->connection) {
            throw new InvalidArgumentException(
                $method . ': Database connection required'
            );
        }
        if (!isset($this->oidMap[$oid])) {
            $this->loadTypes(true);
        }
        if (!isset($this->oidMap[$oid])) {
            throw new InvalidArgumentException(
                sprintf('%s: could not find type information for oid %d', $method, $oid)
            );
        }

        return $this->oidMap[$oid];
    }

    /**
     * Searches for an oid corresponding to the given type name in loaded type metadata
     *
     * @param string      $typeName
     * @param string|null $schemaName
     * @param string      $method     Used in Exception messages only
     * @return int
     * @throws InvalidArgumentException
     */
    protected function findOidForTypeName($typeName, $schemaName, $method)
    {
        if (!$this->connection) {
            throw new InvalidArgumentException(sprintf(
                "%s: Database connection required to process type name %s",
                $method,
                $this->formatQualifiedName($typeName, $schemaName)
            ));
        }
        if (!isset($this->dbTypes['names'][$typeName])
            || null !== $schemaName && !isset($this->dbTypes['names'][$typeName][$schemaName])
        ) {
            $this->loadTypes(true);
        }
        if (!isset($this->dbTypes['names'][$typeName])
            || null !== $schemaName && !isset($this->dbTypes['names'][$typeName][$schemaName])
        ) {
            throw new InvalidArgumentException(sprintf(
                '%s: type %s does not exist in the database',
                __METHOD__,
                $this->formatQualifiedName($typeName, $schemaName)
            ));
        }

        if ($schemaName) {
            return $this->dbTypes['names'][$typeName][$schemaName];

        } elseif (1 === count($this->dbTypes['names'][$typeName])) {
            return reset($this->dbTypes['names'][$typeName]);

        } else {
            throw new InvalidArgumentException(sprintf(
                '%s: Types named "%s" found in schemas: %s. Qualified name required.',
                $method,
                $typeName,
                implode(', ', array_keys($this->dbTypes['names'][$typeName]))
            ));
        }
    }

    /**
     * ASCII-only lowercasing for type names
     *
     * @param string $string
     * @return string
     */
    private function asciiLowercase($string)
    {
        return strtr($string, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /**
     * Parses possibly schema-qualified or double-quoted type name
     *
     * NB: this method does not employ a full-blown parser, specifically
     * it does not handle type modifiers (except "with/without time zone")
     * and only understands '[]' as an array modifier. Use a Parser-backed Factory
     * in sad_spirit/pg_builder package to process any type name Postgres itself understands.
     *
     * @param string $name
     * @return array structure: array (string schema, string type, bool isArray)
     * @throws InvalidArgumentException
     */
    protected function parseTypeName($name)
    {
        if (false === strpos($name, '.') && false === strpos($name, '"')) {
            // can be an SQL standard type, try known aliases
            $regexp = '(?:(' . implode('|', array_keys(self::SIMPLE_ALIASES)) . ')' // 1
                      . '|(double\\s+precision)' // 2
                      . '|(?:(time|timestamp)(?:\\s+(with|without)\\s+time\\s+zone)?)' // 3,4
                      . '|(national\\s+(?:character|char)(?:\\s*varying)?)' // 5
                      . '|(?:(bit|character|char|nchar)(?:\\s*varying)?))' // 6
                      . '\\s*(\\[\\s*\\])?'; // 7
            if (preg_match('/^' . $regexp . '$/i', $name, $matches)) {
                $isArray = !empty($matches[7]);
                if (!empty($matches[1])) {
                    $typeName = self::SIMPLE_ALIASES[$this->asciiLowercase($matches[1])];
                } elseif (!empty($matches[2])) {
                    $typeName = 'float8';
                } elseif (!empty($matches[3])) {
                    $typeName = $this->asciiLowercase($matches[3])
                                . (0 === strcasecmp($matches[4], 'with') ? 'tz' : '');
                } else {
                    $typeName = 'text';
                }
                return ['pg_catalog', $typeName, $isArray];
            }
        }

        $position   = 0;
        $length     = strlen($name);
        $typeName   = null;
        $schema     = null;
        $isArray    = false;
        $identifier = true;

        while ($position < $length) {
            if ('[' === $name[$position]) {
                if (!$typeName || $isArray || $identifier
                    || !preg_match('/\\[\\s*\\]$/A', $name, $m, 0, $position)
                ) {
                    throw new InvalidArgumentException("Invalid array specification in type name '{$name}'");
                }
                $isArray     = true;
                $position   += strlen($m[0]);
                $identifier  = false;

            } elseif ('.' === $name[$position]) {
                if ($schema || !$typeName || $identifier) {
                    throw new InvalidArgumentException("Extra dots in type name '{$name}'");
                }
                list($schema, $typeName) = [$typeName, null];
                $position++;
                $identifier = true;

            } elseif ('"' === $name[$position]) {
                if (!preg_match('/"((?>[^"]+|"")*)"/A', $name, $m, 0, $position)
                    || !strlen($m[1])
                ) {
                    throw new InvalidArgumentException("Invalid double-quoted string in type name '{$name}'");
                } elseif (!$identifier) {
                    throw new InvalidArgumentException(
                        "Unexpected double-quoted string '{$m[0]}' in type name '{$name}'"
                    );
                }
                $typeName    = strtr($m[1], ['""' => '"']);
                $position   += strlen($m[0]);
                $identifier  = false;

            } elseif (preg_match('/[A-Za-z\x80-\xff_][A-Za-z\x80-\xff_0-9\$]*/A', $name, $m, 0, $position)) {
                if (!$identifier) {
                    throw new InvalidArgumentException("Unexpected identifier '{$m[0]}' in type name '{$name}'");
                }
                $typeName    = $this->asciiLowercase($m[0]);
                $position   += strlen($m[0]);
                $identifier  = false;

            } else {
                throw new InvalidArgumentException("Unexpected symbol '{$name[$position]}' in type name '{$name}'");
            }

            $position += strspn($name, " \r\n\t\f", $position);
        }

        if (!$typeName) {
            throw new InvalidArgumentException("Missing type name in '{$name}'");
        }
        return [$schema, $typeName, $isArray];
    }

    /**
     * Returns an instance of converter explicitly registered for a given type
     *
     * @param string $typeName   type name (as passed to registerConverter())
     * @param string $schemaName schema name (only required if converters for the same
     *                           type name were registered for different schemas)
     * @return TypeConverter
     * @throws InvalidArgumentException
     */
    private function getRegisteredConverterInstance($typeName, $schemaName = null)
    {
        if (null === $schemaName) {
            if (1 < count($this->types[$typeName])) {
                throw new InvalidArgumentException(sprintf(
                    '%s: Converters for type "%s" exist for schemas: %s. Qualified name required.',
                    __METHOD__,
                    $typeName,
                    implode(', ', array_keys($this->types[$typeName]))
                ));
            }
            reset($this->types[$typeName]);
            $schemaName = key($this->types[$typeName]);
        }
        if (empty($this->converters[$typeName][$schemaName])) {
            if ($this->types[$typeName][$schemaName] instanceof TypeConverter) {
                $converter = clone $this->types[$typeName][$schemaName];

            } elseif (is_callable($this->types[$typeName][$schemaName])) {
                $converter = $this->types[$typeName][$schemaName]();

            } else {
                $className = $this->types[$typeName][$schemaName];
                $converter = new $className();
            }

            $this->updateConnection($converter);
            $this->converters[$typeName][$schemaName] = $converter;
        }
        return $this->converters[$typeName][$schemaName];
    }

    /**
     * Returns a converter for a (possibly qualified) type name supplied as string
     *
     * Name can be either a "known" base type name from $_types property,
     * 'foo[]' for an array of base type 'foo' or a database-specific name
     * (e.g. for composite type)
     *
     * @param string $name
     * @return TypeConverter
     * @throws InvalidArgumentException
     */
    private function getConverterForTypeName($name)
    {
        if (isset($this->parsedNames[$name])) {
            list($typeName, $schemaName, $isArray) = $this->parsedNames[$name];

        } else {
            if (!preg_match('/^([A-Za-z\x80-\xff_][A-Za-z\x80-\xff_0-9\$]*)(\[\])?$/', $name, $m)) {
                list ($schemaName, $typeName, $isArray) = $this->parseTypeName(trim($name));

            } else {
                $schemaName = null;
                $isArray    = !empty($m[2]);
                $typeName   = $this->asciiLowercase($m[1]);
                if (isset(self::SIMPLE_ALIASES[$typeName])) {
                    $typeName = self::SIMPLE_ALIASES[$typeName];
                }
            }

            $this->parsedNames[$name] = [$typeName, $schemaName, $isArray];
        }

        return $this->getConverterForQualifiedName($typeName, $schemaName, $isArray);
    }

    /**
     * Returns type converter for separately supplied type and schema names
     *
     * @param string $typeName
     * @param string $schemaName
     * @param bool   $isArray
     * @return TypeConverter
     * @throws InvalidArgumentException
     */
    protected function getConverterForQualifiedName($typeName, $schemaName = null, $isArray = false)
    {
        if (isset($this->types[$typeName])
            && (null === $schemaName || isset($this->types[$typeName][$schemaName]))
        ) {
            $converter = $this->getRegisteredConverterInstance($typeName, $schemaName);

        } elseif (!$this->isBaseTypeOid(
            $oid = $this->findOidForTypeName($typeName, $schemaName, __METHOD__)
        )) {
            $converter = $this->getConverterForTypeOid($oid);

        } else {
            // a converter required by name is required explicitly -> exception if not found
            throw new InvalidArgumentException(sprintf(
                '%s: no converter registered for base type %s',
                __METHOD__,
                $this->formatQualifiedName($typeName, $schemaName)
            ));
        }

        return $isArray ? new containers\ArrayConverter($converter) : $converter;
    }

    /**
     * Formats qualified type name (for usage in exception messages)
     *
     * @param string $typeName
     * @param string $schemaName
     * @return string
     */
    private function formatQualifiedName($typeName, $schemaName)
    {
        return (null === $schemaName ? '' : '"' . strtr($schemaName, ['"' => '""']) . '".')
               . '"' . strtr($typeName, ['"' => '""']) . '"';
    }

    /**
     * Returns a converter for a database-specific composite type
     *
     * @param int $oid
     * @return TypeConverter
     * @throws InvalidQueryException
     */
    private function getConverterForCompositeTypeOid($oid)
    {
        if (!is_array($this->dbTypes['composite'][$oid])) {
            if (($cache = $this->connection->getMetadataCache()) && $this->getCompositeTypesCaching()) {
                $cacheItem = $cache->getItem($this->connection->getConnectionId() . '-composite-' . $oid);
            } else {
                $cacheItem = null;
            }

            if (null !== $cacheItem && $cacheItem->isHit()) {
                $this->dbTypes['composite'][$oid] = $cacheItem->get();

            } else {
                $sql = <<<SQL
select attname, atttypid
from pg_catalog.pg_attribute
where attrelid = $1 and
      attnum > 0
order by attnum
SQL;
                if (!($res = @pg_query_params(
                    $this->connection->getResource(),
                    $sql,
                    [$this->dbTypes['composite'][$oid]]
                ))) {
                    throw new InvalidQueryException(pg_last_error($this->connection->getResource()));
                }
                $this->dbTypes['composite'][$oid] = [];
                while ($row = pg_fetch_assoc($res)) {
                    $this->dbTypes['composite'][$oid][$row['attname']] = $row['atttypid'];
                }
                pg_free_result($res);

                if ($cache && $cacheItem) {
                    $cache->save($cacheItem->set($this->dbTypes['composite'][$oid]));
                }
            }
        }

        return $this->getConverter($this->dbTypes['composite'][$oid]);
    }


    /**
     * Populates the types list from pg_catalog.pg_type table
     *
     * @param bool $force Force loading from database even if cached list is available
     * @throws InvalidQueryException
     */
    private function loadTypes($force = false)
    {
        if ($cache = $this->connection->getMetadataCache()) {
            $cacheItem = $cache->getItem($this->connection->getConnectionId() . '-types');
        } else {
            $cacheItem = null;
        }

        if (!$force && null !== $cacheItem && $cacheItem->isHit()) {
            $this->dbTypes = $cacheItem->get();

        } else {
            $this->dbTypes = [
                'composite' => [],
                'array'     => [],
                'range'     => [],
                'names'     => []
            ];
            $sql = <<<SQL
    select t.oid, nspname, typname, typarray, typrelid
    from pg_catalog.pg_type as t, pg_catalog.pg_namespace as s
    where t.typnamespace = s.oid and
          typtype != 'd'
    order by 4 desc
SQL;
            if (!($res = @pg_query($this->connection->getResource(), $sql))) {
                throw new InvalidQueryException(pg_last_error($this->connection->getResource()));
            }
            while ($row = pg_fetch_assoc($res)) {
                if (!isset($this->dbTypes['names'][$row['typname']])) {
                    $this->dbTypes['names'][$row['typname']] = [$row['nspname'] => $row['oid']];
                } else {
                    $this->dbTypes['names'][$row['typname']][$row['nspname']] = $row['oid'];
                }
                if ('0' !== $row['typarray']) {
                    $this->dbTypes['array'][$row['typarray']] = $row['oid'];
                }
                if ('0' !== $row['typrelid']) {
                    $this->dbTypes['composite'][$row['oid']] = $row['typrelid'];
                }
            }
            pg_free_result($res);

            if ($this->getCompositeTypesCaching()) {
                // Preload columns for free-standing composite types: they are far more likely to appear
                // in result sets than those linked to tables.
                $sql = <<<SQL
select reltype, attname, atttypid
from pg_catalog.pg_attribute as a, pg_catalog.pg_class as c
where a.attrelid = c.oid and
      c.relkind  = 'c' and
      a.attnum > 0
order by attrelid, attnum
SQL;
                if (!($res = @pg_query($this->connection->getResource(), $sql))) {
                    throw new InvalidQueryException(pg_last_error($this->connection->getResource()));
                }
                while ($row = pg_fetch_assoc($res)) {
                    if (!is_array($this->dbTypes['composite'][$row['reltype']])) {
                        $this->dbTypes['composite'][$row['reltype']] = [$row['attname'] => $row['atttypid']];
                    } else {
                        $this->dbTypes['composite'][$row['reltype']][$row['attname']] = $row['atttypid'];
                    }
                }
                pg_free_result($res);
            }

            if (!($res = @pg_query($this->connection->getResource(), "select rngtypid, rngsubtype from pg_range"))) {
                throw new InvalidQueryException(pg_last_error($this->connection->getResource()));
            }
            while ($row = pg_fetch_assoc($res)) {
                $this->dbTypes['range'][$row['rngtypid']] = $row['rngsubtype'];
            }
            pg_free_result($res);

            if ($cache && $cacheItem) {
                $cache->save($cacheItem->set($this->dbTypes));
            }
        }

        $this->oidMap = [];
        foreach ($this->dbTypes['names'] as $typeName => $schemas) {
            foreach ($schemas as $schemaName => $oid) {
                $this->oidMap[$oid] = [$schemaName, $typeName];
            }
        }
    }
}
