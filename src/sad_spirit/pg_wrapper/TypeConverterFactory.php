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
 * @copyright 2014 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

namespace sad_spirit\pg_wrapper;

use sad_spirit\pg_builder\nodes\TypeName,
    sad_spirit\pg_builder\nodes\QualifiedName,
    sad_spirit\pg_builder\nodes\IntervalTypeName;

/**
 * Creates type converters for database type based on specific DB metadata
 */
class TypeConverterFactory
{
    /**
     * Mapping from one-word SQL standard types to native types
     * @var array
     */
    private $_simpleAliases = array(
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
    );

    /**
     * DB connection object
     * @var Connection
     */
    private $_connection;

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
    private $_dbTypes = array(
        'composite' => array(),
        'array'     => array(),
        'range'     => array(),
        'names'     => array()
    );

    /**
     * Mapping type oid => array('schema name', 'type name')
     *
     * This is built based on _dbTypes['names'], but not saved to cache
     *
     * @var array
     */
    private $_oidMap = array();

    /**
     * Mapping of known base types to converter class names
     * @var array
     */
    private $_types = array();

    /**
     * Converter instances
     * @var array
     */
    private $_converters = array();

    /**
     * Constructor, registers converters for built-in types
     */
    public function __construct()
    {
        $this->registerConverter('\sad_spirit\pg_wrapper\converters\BooleanConverter', 'bool');
        $this->registerConverter('\sad_spirit\pg_wrapper\converters\ByteaConverter', 'bytea');
        $this->registerConverter(
            '\sad_spirit\pg_wrapper\converters\IntegerConverter',
            array('oid', 'cid', 'xid', 'int2', 'int4', 'int8')
        );
        $this->registerConverter('\sad_spirit\pg_wrapper\converters\NumericConverter', 'numeric');
        $this->registerConverter(
            '\sad_spirit\pg_wrapper\converters\FloatConverter',
            array('float4', 'float8', 'money')
        );
        $this->registerConverter('\sad_spirit\pg_wrapper\converters\datetime\DateConverter', 'date');
        $this->registerConverter('\sad_spirit\pg_wrapper\converters\datetime\TimeConverter', 'time');
        $this->registerConverter('\sad_spirit\pg_wrapper\converters\datetime\TimeTzConverter', 'timetz');
        $this->registerConverter('\sad_spirit\pg_wrapper\converters\datetime\TimeStampConverter', 'timestamp');
        $this->registerConverter(
            '\sad_spirit\pg_wrapper\converters\datetime\TimeStampTzConverter',
            array('timestamptz', 'abstime')
        );
        $this->registerConverter(
            '\sad_spirit\pg_wrapper\converters\datetime\IntervalConverter',
            array('interval', 'reltime')
        );
        $this->registerConverter(
            '\sad_spirit\pg_wrapper\converters\StringConverter',
            array('cstring', 'text', 'char', 'varchar', 'bpchar', 'name')
        );
        $this->registerConverter('\sad_spirit\pg_wrapper\converters\TidConverter', 'tid');

        $this->registerConverter('\sad_spirit\pg_wrapper\converters\geometric\PointConverter', 'point');
        $this->registerConverter('\sad_spirit\pg_wrapper\converters\geometric\CircleConverter', 'circle');
        $this->registerConverter('\sad_spirit\pg_wrapper\converters\geometric\LineConverter', 'line');
        $this->registerConverter('\sad_spirit\pg_wrapper\converters\geometric\LSegConverter', 'lseg');
        $this->registerConverter('\sad_spirit\pg_wrapper\converters\geometric\BoxConverter', 'box');
        $this->registerConverter('\sad_spirit\pg_wrapper\converters\geometric\PathConverter', 'path');
        $this->registerConverter('\sad_spirit\pg_wrapper\converters\geometric\PolygonConverter', 'polygon');

        $this->registerConverter(
            '\sad_spirit\pg_wrapper\converters\containers\HstoreConverter',
            'hstore', 'public'
        );

        $this->registerConverter(
            '\sad_spirit\pg_wrapper\converters\JSONConverter',
            array('json', 'jsonb')
        );

        $this->registerConverter(function () {
            return new converters\containers\RangeConverter(new converters\IntegerConverter());
        }, array('int4range', 'int8range'));
        $this->registerConverter(function () {
            return new converters\containers\RangeConverter(new converters\NumericConverter());
        }, 'numrange');
        $this->registerConverter(function () {
            return new converters\containers\RangeConverter(new converters\datetime\DateConverter());
        }, 'daterange');
        $this->registerConverter(function () {
            return new converters\containers\RangeConverter(new converters\datetime\TimeStampConverter());
        }, 'tsrange');
        $this->registerConverter(function () {
            return new converters\containers\RangeConverter(new converters\datetime\TimeStampTzConverter());
        }, 'tstzrange');
    }

    /**
     * Registers converter for a known named type
     *
     * @param string|callable|TypeConverter $converter
     * @param string|array                  $type
     * @param string                        $schema
     * @throws exceptions\InvalidArgumentException
     */
    public function registerConverter($converter, $type, $schema = 'pg_catalog')
    {
        if (!is_string($converter) && !is_callable($converter) && !($converter instanceof TypeConverter)) {
            throw new exceptions\InvalidArgumentException(sprintf(
                '%s() expects a class name, a closure or an instance of TypeConverter, %s given',
                __METHOD__, is_object($converter) ? 'object(' . get_class($converter) . ')' : gettype($converter)
            ));
        }
        foreach ((array)$type as $typeName) {
            if (isset($this->_converters[$typeName])) {
                unset($this->_converters[$typeName][$schema]);
            }
            if (!isset($this->_types[$typeName])) {
                $this->_types[$typeName] = array($schema => $converter);
            } else {
                $this->_types[$typeName][$schema] = $converter;
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
     */
    public function setConnection(Connection $connection)
    {
        if (!empty($this->_connection)) {
            // prevent reusing old converters with new connection
            $this->_converters = array();
        }

        $this->_connection = $connection;
        foreach ($this->_converters as $converter) {
            $this->_updateConnection($converter);
        }

        $this->_loadTypes();
    }

    /**
     * Updates connection data for ConnectionAware converter
     *
     * @param TypeConverter $converter
     */
    private function _updateConnection(TypeConverter $converter)
    {
        if ($this->_connection && $converter instanceof converters\ConnectionAware) {
            $converter->setConnectionResource($this->_connection->getResource());
        }
    }

    /**
     * Returns a converter for a given database type
     *
     * $type can be either of
     *  - type oid (integer)
     *  - type name (string), either simple or schema-qualified,
     *    'foo[]' is treated as an array of base type 'foo'
     *  - array('field' => 'type', ...) for composite types
     *  - TypeName AST node
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
     *  - if type oid was used, a fallback converter (StringConverter) will
     *    be returned
     *
     * This allows unknown types in ResultSet to be returned as strings, as
     * pgsql extension itself does, while at the same time prevents errors
     * when manually requesting converters for a type.
     *
     * @param mixed $type
     * @return TypeConverter
     * @throws exceptions\InvalidArgumentException
     */
    public function getConverter($type)
    {
        if ($type instanceof TypeConverter) {
            $this->_updateConnection($type);
            return $type;

        } elseif ($type instanceof TypeName) {
            return $this->_getConverterForTypeNameNode($type);

        } elseif (is_scalar($type)) {
            if (ctype_digit((string)$type)) {
                // type oid given
                return $this->_getConverterForTypeOid($type);
            } else {
                // type name given
                return $this->_getConverterForTypeName($type);
            }

        } elseif (is_array($type)) {
            // type specification for composite type
            $types = array();
            foreach ($type as $k => $v) {
                $types[$k] = $this->getConverter($v);
            }
            return new converters\containers\CompositeConverter($types);
        }

        throw new exceptions\InvalidArgumentException(sprintf(
            '%s expects either of: type oid, type name, composite type array,'
            . ' instance of TypeConverter or query\nodes\TypeName. %s given',
            __METHOD__, is_object($type) ? 'object(' . get_class($type) . ')' : gettype($type)
        ));
    }

    /**
     * Returns a converter for query builder's TypeName node
     *
     * Usually this will come from a typecast applied to a query parameter and
     * extracted by ParameterWalker
     *
     * @param TypeName $typeName
     * @return TypeConverter|converters\containers\ArrayConverter
     */
    private function _getConverterForTypeNameNode(TypeName $typeName)
    {
        if ($typeName instanceof IntervalTypeName) {
            return $this->_getConverterForQualifiedName(
                'interval', 'pg_catalog', count($typeName->bounds) > 0
            );

        } else {
            return $this->_getConverterForQualifiedName(
                $typeName->name->relation->value,
                $typeName->name->schema ? $typeName->name->schema->value : null,
                count($typeName->bounds) > 0
            );
        }
    }

    /**
     * Returns a converter for a database type identified by oid
     *
     * @param int $oid
     * @return TypeConverter
     * @throws exceptions\InvalidArgumentException
     */
    private function _getConverterForTypeOid($oid)
    {
        if (!$this->_connection) {
            throw new exceptions\InvalidArgumentException(
                __METHOD__ . ': Database connection required'
            );
        }

        if (!array_key_exists($oid, $this->_oidMap)) {
            $this->_loadTypes(true);
        }

        if (array_key_exists($oid, $this->_dbTypes['array'])) {
            return new converters\containers\ArrayConverter(
                $this->_getConverterForTypeOid($this->_dbTypes['array'][$oid])
            );

        } elseif (array_key_exists($oid, $this->_dbTypes['range'])) {
            return new converters\containers\RangeConverter(
                $this->_getConverterForTypeOid($this->_dbTypes['range'][$oid])
            );

        } elseif (array_key_exists($oid, $this->_dbTypes['composite'])) {
            return $this->_getConverterForCompositeTypeOid($oid);

        } elseif (array_key_exists($oid, $this->_oidMap)) {
            try {
                list ($schemaName, $typeName) = $this->_oidMap[$oid];
                return $this->_getConverterForQualifiedName($typeName, $schemaName);
            } catch (exceptions\InvalidArgumentException $e) {
                return new converters\StringConverter();
            }
        }

        throw new exceptions\InvalidArgumentException(sprintf(
            '%s: could not find type information for oid %d', __METHOD__, $oid
        ));
    }

    /**
     * ASCII-only lowercasing for type names
     *
     * @param string $string
     * @return string
     */
    private function _asciiLowercase($string)
    {
        return strtr($string, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /**
     * Parses possibly schema-qualified or double-quoted type name
     *
     * NB: this method does not employ a full-blown parser, specifically
     * it does not handle type modifiers (except "with/without time zone")
     * and only understands '[]' as an array modifier. Parser should be used
     * to process any type name Postgres itself understands.
     *
     * @param string $name
     * @return array structure: array (string schema, string type, bool isArray)
     * @throws exceptions\InvalidArgumentException
     */
    private function _parseTypeName($name)
    {
        if (false === strpos($name, '.') && false === strpos($name, '"')) {
            // can be an SQL standard type, try known aliases
            $regexp = '(?:(' . implode('|', array_keys($this->_simpleAliases)) . ')' // 1
                      . '|(double\\s+precision)' // 2
                      . '|(?:(time|timestamp)(?:\\s+(with|without)\\s+time\\s+zone)?)' // 3,4
                      . '|(national\\s+(?:character|char)(?:\\s*varying)?)' // 5
                      . '|(?:(bit|character|char|nchar)(?:\\s*varying)?))' // 6
                      . '\\s*(\\[\\s*\\])?'; // 7
            if (preg_match('/^' . $regexp . '$/i', $name, $matches)) {
                $isArray = !empty($matches[7]);
                if (!empty($matches[1])) {
                    $typeName = $this->_simpleAliases[$this->_asciiLowercase($matches[1])];
                } elseif (!empty($matches[2])) {
                    $typeName = 'float8';
                } elseif (!empty($matches[3])) {
                    $typeName = $this->_asciiLowercase($matches[3])
                                . (0 === strcasecmp($matches[4], 'with') ? 'tz' : '');
                } else {
                    $typeName = 'text';
                }
                return array('pg_catalog', $typeName, $isArray);
            }
        }

        $position   = 0;
        $strlen     = function_exists('mb_orig_strlen') ? 'mb_orig_strlen' : 'strlen';
        $length     = $strlen($name);
        $typeName   = null;
        $schema     = null;
        $isArray    = false;
        $identifier = true;

        while ($position < $length) {
            if ('[' === $name[$position]) {
                if (!$typeName || $isArray || $identifier
                    || !preg_match('/\\[\\s*\\]$/A', $name, $m, 0, $position)
                ) {
                    throw new exceptions\InvalidArgumentException("Invalid array specification in type name '{$name}'");
                }
                $isArray     = true;
                $position   += $strlen($m[0]);
                $identifier  = false;

            } elseif ('.' === $name[$position]) {
                if ($schema || !$typeName || $identifier) {
                    throw new exceptions\InvalidArgumentException("Extra dots in type name '{$name}'");
                }
                list($schema, $typeName) = array($typeName, null);
                $position++;
                $identifier = true;

            } elseif ('"' === $name[$position]) {
                if (!preg_match('/"((?>[^"]+|"")*)"/A', $name, $m, 0, $position)
                    || !$strlen($m[1])
                ) {
                    throw new exceptions\InvalidArgumentException("Invalid double-quoted string in type name '{$name}'");
                } elseif (!$identifier) {
                    throw new exceptions\InvalidArgumentException("Unexpected double-quoted string '{$m[0]}' in type name '{$name}'");
                }
                $typeName    = strtr($m[1], array('""' => '"'));
                $position   += $strlen($m[0]);
                $identifier  = false;

            } elseif (preg_match('/[A-Za-z\x80-\xff_][A-Za-z\x80-\xff_0-9\$]*/A', $name, $m, 0, $position)) {
                if (!$identifier) {
                    throw new exceptions\InvalidArgumentException("Unexpected identifier '{$m[0]}' in type name '{$name}'");
                }
                $typeName    = $this->_asciiLowercase($m[0]);
                $position   += $strlen($m[0]);
                $identifier  = false;

            } else {
                throw new exceptions\InvalidArgumentException("Unexpected symbol '{$name[$position]}' in type name '{$name}'");
            }

            $position += strspn($name, " \r\n\t\f", $position);
        }

        if (!$typeName) {
            throw new exceptions\InvalidArgumentException("Missing type name in '{$name}'");
        }
        return array($schema, $typeName, $isArray);
    }

    /**
     * Returns an instance of converter explicitly registered for a given type
     *
     * @param string $typeName   type name (as passed to registerConverter())
     * @param string $schemaName schema name (only required if converters for the same
     *                           type name were registered for different schemas)
     * @return TypeConverter
     * @throws exceptions\InvalidArgumentException
     */
    private function _getRegisteredConverterInstance($typeName, $schemaName = null)
    {
        if (null === $schemaName) {
            if (1 < count($this->_types[$typeName])) {
                throw new exceptions\InvalidArgumentException(sprintf(
                    '%s: Converters for type "%s" exist for schemas: %s. Qualified name required.',
                    __METHOD__, $typeName, implode(', ', array_keys($this->_types[$typeName]))
                ));
            }
            reset($this->_types[$typeName]);
            $schemaName = key($this->_types[$typeName]);
        }
        if (empty($this->_converters[$typeName][$schemaName])) {
            if ($this->_types[$typeName][$schemaName] instanceof TypeConverter) {
                $converter = clone $this->_types[$typeName][$schemaName];

            } elseif (is_callable($this->_types[$typeName][$schemaName])) {
                $converter = $this->_types[$typeName][$schemaName]();

            } else {
                $className = $this->_types[$typeName][$schemaName];
                $converter = new $className;
            }

            $this->_updateConnection($converter);
            $this->_converters[$typeName][$schemaName] = $converter;
        }
        return $this->_converters[$typeName][$schemaName];
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
     * @throws exceptions\InvalidArgumentException
     */
    private function _getConverterForTypeName($name)
    {
        if (!preg_match('/^([A-Za-z\x80-\xff_][A-Za-z\x80-\xff_0-9\$]*)(\[\])?$/', $name, $m)) {
            list ($schemaName, $typeName, $isArray) = $this->_parseTypeName(trim($name));

        } else {
            $schemaName = null;
            $isArray    = !empty($m[2]);
            $typeName   = $this->_asciiLowercase($m[1]);
            if (isset($this->_simpleAliases[$typeName])) {
                $typeName = $this->_simpleAliases[$typeName];
            }
        }

        return $this->_getConverterForQualifiedName($typeName, $schemaName, $isArray);
    }

    /**
     * Returns type converter for separately supplied type and schema names
     *
     * @param string $typeName
     * @param string $schemaName
     * @param bool   $isArray
     * @return TypeConverter
     * @throws exceptions\InvalidArgumentException
     */
    private function _getConverterForQualifiedName($typeName, $schemaName = null, $isArray = false)
    {
        if (isset($this->_types[$typeName])
            && (null === $schemaName || isset($this->_types[$typeName][$schemaName]))
        ) {
            $converter = $this->_getRegisteredConverterInstance($typeName, $schemaName);

        } elseif (!$this->_connection) {
            throw new exceptions\InvalidArgumentException(sprintf(
                "%s: Database connection required to process type name %s",
                __METHOD__, $this->_formatQualifiedName($typeName, $schemaName)
            ));

        } else {
            if (!isset($this->_dbTypes['names'][$typeName])
                || null !== $schemaName && !isset($this->_dbTypes['names'][$typeName][$schemaName])
            ) {
                $this->_loadTypes(true);
            }
            if (!isset($this->_dbTypes['names'][$typeName])
                || null !== $schemaName && !isset($this->_dbTypes['names'][$typeName][$schemaName])
            ) {
                throw new exceptions\InvalidArgumentException(sprintf(
                    '%s: type %s does not exist in the database',
                    __METHOD__, $this->_formatQualifiedName($typeName, $schemaName)
                ));
            }

            if ($schemaName) {
                $oid = $this->_dbTypes['names'][$typeName][$schemaName];

            } elseif (1 === count($this->_dbTypes['names'][$typeName])) {
                $oid = reset($this->_dbTypes['names'][$typeName]);

            } else {
                throw new exceptions\InvalidArgumentException(sprintf(
                    '%s: Types named "%s" found in schemas: %s. Qualified name required.',
                    __METHOD__, $typeName, implode(', ', array_keys($this->_dbTypes['names'][$typeName]))
                ));
            }

            if (array_key_exists($oid, $this->_dbTypes['array'])
                || array_key_exists($oid, $this->_dbTypes['range'])
                || array_key_exists($oid, $this->_dbTypes['composite'])
            ) {
                $converter = $this->_getConverterForTypeOid($oid);

            } else {
                // a converter required by name is required explicitly -> exception if not found
                throw new exceptions\InvalidArgumentException(sprintf(
                    '%s: no converter registered for base type %s',
                    __METHOD__, $this->_formatQualifiedName($typeName, $schemaName)
                ));
            }
        }

        return $isArray ? new converters\containers\ArrayConverter($converter) : $converter;
    }

    /**
     * Formats qualified type name (for usage in exception messages)
     *
     * @param string $typeName
     * @param string $schemaName
     * @return string
     */
    private function _formatQualifiedName($typeName, $schemaName)
    {
        return (null === $schemaName ? '' : '"' . strtr($schemaName, array('"' => '""')) . '".')
               . '"' . strtr($typeName, array('"' => '""')) . '"';
    }

    /**
     * Returns a converter for a database-specific composite type
     *
     * @param int $oid
     * @return TypeConverter
     * @throws exceptions\InvalidQueryException
     */
    private function _getConverterForCompositeTypeOid($oid)
    {
        if (!is_array($this->_dbTypes['composite'][$oid])) {
            $sql = <<<SQL
select attname, atttypid
from pg_catalog.pg_attribute
where attrelid = $1 and
      attnum > 0
order by attnum
SQL;
            if (!($res = @pg_query_params(
                    $this->_connection->getResource(), $sql , array($this->_dbTypes['composite'][$oid])
                ))
            ) {
                throw new exceptions\InvalidQueryException(pg_last_error($this->_connection->getResource()));
            }
            $this->_dbTypes['composite'][$oid] = array();
            while ($row = pg_fetch_assoc($res)) {
                $this->_dbTypes['composite'][$oid][$row['attname']] = $row['atttypid'];
            }
            pg_free_result($res);
        }

        return $this->getConverter($this->_dbTypes['composite'][$oid]);
    }


    /**
     * Returns TypeName node for query AST based on provided type oid
     *
     * @param int $oid
     * @return TypeName
     * @throws exceptions\InvalidArgumentException
     */
    public function getTypeNameForOid($oid)
    {
        if (!$this->_connection) {
            throw new exceptions\InvalidArgumentException(
                __METHOD__ . ': Database connection required'
            );
        }

        if (!array_key_exists($oid, $this->_oidMap)) {
            $this->_loadTypes(true);
        }

        if (array_key_exists($oid, $this->_dbTypes['array'])) {
            $base = $this->getTypeNameForOid($this->_dbTypes['array'][$oid]);
            $base->setArrayBounds(array(-1));
            return $base;

        } elseif (array_key_exists($oid, $this->_oidMap)) {
            list ($schemaName, $typeName) = $this->_oidMap[$oid];
            if ('pg_catalog' !== $schemaName) {
                return new TypeName(new QualifiedName(array($schemaName, $typeName)));
            } else {
                return new TypeName(new QualifiedName(array($typeName)));
            }
        }

        throw new exceptions\InvalidArgumentException(sprintf(
            '%s: could not find type information for oid %d', __METHOD__, $oid
        ));
    }

    /**
     * Populates the types list from pg_catalog.pg_type table
     *
     * @param bool $force Force loading from database even if cached list is available
     * @throws exceptions\InvalidQueryException
     */
    private function _loadTypes($force = false)
    {
        $cacheKey = $this->_connection->getConnectionId() . '-types';

        if (!($cache = $this->_connection->getMetadataCache())
            || $force || null === ($this->_dbTypes = $cache->getItem($cacheKey))
        ) {
            $this->_dbTypes = array(
                'composite' => array(),
                'array'     => array(),
                'range'     => array(),
                'names'     => array()
            );
            $sql = <<<SQL
    select t.oid, nspname, typname, typarray, typrelid
    from pg_catalog.pg_type as t, pg_catalog.pg_namespace as s
    where t.typnamespace = s.oid and
          typtype != 'd'
    order by 4 desc
SQL;
            if (!($res = @pg_query($this->_connection->getResource(), $sql))) {
                throw new exceptions\InvalidQueryException(pg_last_error($this->_connection->getResource()));
            }
            while ($row = pg_fetch_assoc($res)) {
                if (!isset($this->_dbTypes['names'][$row['typname']])) {
                    $this->_dbTypes['names'][$row['typname']] = array($row['nspname'] => $row['oid']);
                } else {
                    $this->_dbTypes['names'][$row['typname']][$row['nspname']] = $row['oid'];
                }
                if ('0' !== $row['typarray']) {
                    $this->_dbTypes['array'][$row['typarray']] = $row['oid'];
                }
                if ('0' !== $row['typrelid']) {
                    $this->_dbTypes['composite'][$row['oid']] = $row['typrelid'];
                }
            }
            pg_free_result($res);

            if (version_compare(
                    pg_parameter_status($this->_connection->getResource(), 'server_version'), '9.2.0', '>='
            )) {
                if (!($res = @pg_query(
                    $this->_connection->getResource(), "select rngtypid, rngsubtype from pg_range"
                ))) {
                    throw new exceptions\InvalidQueryException(pg_last_error($this->_connection->getResource()));
                }
                while ($row = pg_fetch_assoc($res)) {
                    $this->_dbTypes['range'][$row['rngtypid']] = $row['rngsubtype'];
                }
            }

            if ($cache) {
                $cache->setItem($cacheKey, $this->_dbTypes);
            }
        }

        $this->_oidMap = array();
        foreach ($this->_dbTypes['names'] as $typeName => $schemas) {
            foreach ($schemas as $schemaName => $oid) {
                $this->_oidMap[$oid] = array($schemaName, $typeName);
            }
        }
    }
}
