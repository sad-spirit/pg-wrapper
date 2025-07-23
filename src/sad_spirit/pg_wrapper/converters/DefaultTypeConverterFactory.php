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
    Connection,
    exceptions\InvalidArgumentException,
    exceptions\RuntimeException,
    exceptions\TypeConversionException,
    types
};

/**
 * Creates type converters for database type based on specific DB metadata
 */
class DefaultTypeConverterFactory implements ConfigurableTypeConverterFactory
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

    /** DB connection object */
    private ?Connection $connection = null;

    /**
     * Mapping of known base types to converter class names
     * @var array<string, array<string, class-string|callable|TypeConverter>>
     */
    private array $types = [];

    /**
     * Converter instances
     * @var array<string, array<string, TypeConverter>>
     */
    private array $converters = [];

    /**
     * Mapping "type name as string" => ["type name", "schema name", "is array"]
     * @var array<string, array{string, ?string, bool}>
     */
    private array $parsedNames = [];

    /**
     * Mapping "PHP class name" => ["schema name", "type name"]
     * @var array<class-string, array{string, string}>
     */
    private array $classMapping = [
        \DateTimeInterface::class       => ['pg_catalog', 'timestamptz'],
        \DateInterval::class            => ['pg_catalog', 'interval'],

        types\Box::class                => ['pg_catalog', 'box'],
        types\Circle::class             => ['pg_catalog', 'circle'],
        types\Line::class               => ['pg_catalog', 'line'],
        types\LineSegment::class        => ['pg_catalog', 'lseg'],
        types\Path::class               => ['pg_catalog', 'path'],
        types\Point::class              => ['pg_catalog', 'point'],
        types\Polygon::class            => ['pg_catalog', 'polygon'],
        types\DateTimeRange::class      => ['pg_catalog', 'tstzrange'],
        types\DateTimeMultiRange::class => ['pg_catalog', 'tstzmultirange'],
        types\NumericRange::class       => ['pg_catalog', 'numrange'],
        types\NumericMultiRange::class  => ['pg_catalog', 'nummultirange'],
        types\Tid::class                => ['pg_catalog', 'tid']
    ];

    private ?TypeOIDMapper $typeOIDMapper = null;

    /**
     * Constructor, registers converters for built-in types
     */
    public function __construct()
    {
        $this->registerConverter(BooleanConverter::class, 'bool');
        $this->registerConverter(ByteaConverter::class, 'bytea');
        $this->registerConverter(
            IntegerConverter::class,
            ['oid', 'cid', 'xid', 'int2', 'int4', 'int8']
        );
        $this->registerConverter(NumericConverter::class, ['numeric', 'money']);
        $this->registerConverter(FloatConverter::class, ['float4', 'float8']);
        $this->registerConverter(datetime\DateConverter::class, 'date');
        $this->registerConverter(datetime\TimeConverter::class, 'time');
        $this->registerConverter(datetime\TimeTzConverter::class, 'timetz');
        $this->registerConverter(datetime\TimeStampConverter::class, 'timestamp');
        $this->registerConverter(datetime\TimeStampTzConverter::class, 'timestamptz');
        $this->registerConverter(datetime\IntervalConverter::class, 'interval');
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

        $this->registerConverter(static function () {
            return new containers\RangeConverter(new IntegerConverter());
        }, ['int4range', 'int8range']);
        $this->registerConverter(static function () {
            return new containers\RangeConverter(new NumericConverter());
        }, 'numrange');
        $this->registerConverter(static function () {
            return new containers\RangeConverter(new datetime\DateConverter());
        }, 'daterange');
        $this->registerConverter(static function () {
            return new containers\RangeConverter(new datetime\TimeStampConverter());
        }, 'tsrange');
        $this->registerConverter(static function () {
            return new containers\RangeConverter(new datetime\TimeStampTzConverter());
        }, 'tstzrange');

        $this->registerConverter(static function () {
            return new containers\MultiRangeConverter(new IntegerConverter());
        }, ['int4multirange', 'int8multirange']);
        $this->registerConverter(static function () {
            return new containers\MultiRangeConverter(new NumericConverter());
        }, 'nummultirange');
        $this->registerConverter(static function () {
            return new containers\MultiRangeConverter(new datetime\DateConverter());
        }, 'datemultirange');
        $this->registerConverter(static function () {
            return new containers\MultiRangeConverter(new datetime\TimeStampConverter());
        }, 'tsmultirange');
        $this->registerConverter(static function () {
            return new containers\MultiRangeConverter(new datetime\TimeStampTzConverter());
        }, 'tstzmultirange');

        $this->registerConverter(containers\IntegerVectorConverter::class, ['int2vector', 'oidvector']);
    }

    public function setOIDMapper(TypeOIDMapper $mapper): void
    {
        if ($mapper instanceof ConnectionAware && null !== $this->connection) {
            $mapper->setConnection($this->connection);
        }
        $this->typeOIDMapper = $mapper;
    }

    public function getOIDMapper(): TypeOIDMapper
    {
        return $this->typeOIDMapper ??= new CachedTypeOIDMapper($this->connection);
    }

    /**
     * Registers a converter for a known named type
     *
     * @param class-string<TypeConverter>|callable|TypeConverter $converter
     * @param string|string[]                     $type
     */
    public function registerConverter(
        callable|TypeConverter|string $converter,
        array|string $type,
        string $schema = 'pg_catalog'
    ): void {
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
     * Registers a mapping between PHP class and database type name
     *
     * If an instance of the given class will later be provided to getConverterForPHPValue(), that method will return
     * a converter for the given database type
     *
     * @param class-string $className
     */
    public function registerClassMapping(string $className, string $type, string $schema = 'pg_catalog'): void
    {
        $this->classMapping[$className] = [$schema, $type];
    }

    /**
     * Sets database connection details for this object
     *
     * Database connection is used for reading the types data from the
     * catalog, connection id is used for storing that data in cache.
     */
    public function setConnection(Connection $connection): void
    {
        if ($this->connection && $connection !== $this->connection) {
            throw new RuntimeException("Connection already set");
        }

        $this->connection = $connection;
        foreach ($this->converters as $typeNameConverters) {
            foreach ($typeNameConverters as $converter) {
                $this->updateConnection($converter);
            }
        }

        if ($this->typeOIDMapper instanceof ConnectionAware) {
            $this->typeOIDMapper->setConnection($connection);
        }
    }

    /**
     * Updates connection data for ConnectionAware converter
     */
    private function updateConnection(TypeConverter $converter): void
    {
        if ($this->connection && $converter instanceof ConnectionAware) {
            $converter->setConnection($this->connection);
        }
    }

    /**
     * Returns a converter specified by a given type
     *
     * $type can be either of
     *  - type name (string), either simple or schema-qualified,
     *    'foo[]' is treated as an array of base type 'foo'
     *  - ['field' => 'type', ...] for composite types
     *  - ['' => type] is an alternate way to specify array of given type, which may be also
     *    an array specifying composite type
     *  - TypeConverter instance. If it implements ConnectionAware, then
     *    it will receive current Connection
     *
     * Converters for type names registered with registerConverter() will
     * be returned even without database connection. Getting Converters for
     * database-specific names (e.g. composite types) requires a connection.
     *
     * If no converter was registered for a (base) type, an exception will be thrown
     *
     * @throws InvalidArgumentException
     */
    public function getConverterForTypeSpecification(mixed $type): TypeConverter
    {
        if ($type instanceof TypeConverter) {
            $this->updateConnection($type);
            return $type;

        } elseif (\is_string($type)) {
            return $this->getConverterForTypeName($type);

        } elseif (\is_array($type)) {
            // alternate type specification for arrays, added in 3.2
            if (1 === \count($type) && [''] === \array_keys($type)) {
                return new containers\ArrayConverter($this->getConverterForTypeSpecification(\reset($type)));
            }

            // type specification for composite type
            return new containers\CompositeConverter(\array_map(
                $this->getConverterForTypeSpecification(...),
                $type
            ));
        }

        throw InvalidArgumentException::unexpectedType(
            __METHOD__,
            'either of: type name, composite type array, instance of TypeConverter',
            $type
        );
    }

    /**
     * Tries to return a converter based on type of value
     *
     * This will work for
     *  - nulls
     *  - values of scalar types (string / int / float / bool)
     *  - instances of classes from $classMapping (new ones may be added with registerClassMapping())
     *
     * @throws TypeConversionException
     */
    public function getConverterForPHPValue(mixed $value): TypeConverter
    {
        switch (\gettype($value)) {
            case 'string':
            case 'NULL':
                return $this->getConverterForQualifiedName('text', 'pg_catalog');

            case 'integer':
                return $this->getConverterForQualifiedName('int8', 'pg_catalog');

            case 'double':
                return $this->getConverterForQualifiedName('numeric', 'pg_catalog');

            case 'boolean':
                return $this->getConverterForQualifiedName('bool', 'pg_catalog');

            case 'object':
                foreach ($this->classMapping as $className => [$schemaName, $typeName]) {
                    if ($value instanceof $className) {
                        return $this->getConverterForQualifiedName($typeName, $schemaName);
                    }
                }
        }

        throw TypeConversionException::guessFailed($value);
    }

    /**
     * {@inheritdoc}
     */
    final public function getConverterForTypeOID(int|string $oid): TypeConverter
    {
        $mapper                  = $this->getOIDMapper();
        [$schemaName, $typeName] = $mapper->findTypeNameForOID($oid);

        if (null !== $converter = $this->getRegisteredConverterInstance($typeName, $schemaName)) {
            return $converter;

        } elseif ($mapper->isArrayTypeOID($oid, $baseTypeOid)) {
            return new containers\ArrayConverter(
                $this->getConverterForTypeOID($baseTypeOid)
            );

        } elseif ($mapper->isRangeTypeOID($oid, $baseTypeOid)) {
            return new containers\RangeConverter(
                $this->getConverterForTypeOID($baseTypeOid)
            );

        } elseif ($mapper->isMultiRangeTypeOID($oid, $baseTypeOid)) {
            return new containers\MultiRangeConverter(
                $this->getConverterForTypeOID($baseTypeOid)
            );

        } elseif ($mapper->isCompositeTypeOID($oid, $members)) {
            return new containers\CompositeConverter(\array_map(
                $this->getConverterForTypeOID(...),
                $members
            ));

        } elseif ($mapper->isDomainTypeOID($oid, $baseTypeOid)) {
            return $this->getConverterForTypeOID($baseTypeOid);
        }

        // Base type with no explicitly registered converter: fall back to returning the string representation
        return new StubConverter();
    }

    /**
     * Parses possibly schema-qualified or double-quoted type name
     *
     * NB: this method does not employ a full-blown parser, specifically
     * it does not handle type modifiers (except "with/without time zone")
     * and only understands '[]' as an array modifier. Use a Parser-backed Factory
     * in sad_spirit/pg_builder package to process any type name Postgres itself understands.
     *
     * @return array{?string, string, bool} schema name, type name, array flag
     * @throws InvalidArgumentException
     */
    protected function parseTypeName(string $name): array
    {
        if (!\str_contains($name, '.') && !\str_contains($name, '"')) {
            // can be an SQL standard type, try known aliases
            $regexp = '(?:(' . \implode('|', \array_keys(self::SIMPLE_ALIASES)) . ')' // 1
                      . '|(double\\s+precision)' // 2
                      . '|(time|timestamp)(?:\\s+(with|without)\\s+time\\s+zone)?' // 3,4
                      . '|(national\\s+(?:character|char)(?:\\s*varying)?)' // 5
                      . '|(bit|character|char|nchar)(?:\\s*varying)?)' // 6
                      . '\\s*(\\[\\s*])?'; // 7
            if (\preg_match('/^' . $regexp . '$/i', $name, $matches)) {
                $isArray = !empty($matches[7]);
                if (!empty($matches[1])) {
                    $typeName = self::SIMPLE_ALIASES[\strtolower($matches[1])];
                } elseif (!empty($matches[2])) {
                    $typeName = 'float8';
                } elseif (!empty($matches[3])) {
                    $typeName = \strtolower($matches[3]) . (0 === \strcasecmp($matches[4], 'with') ? 'tz' : '');
                } else {
                    $typeName = 'text';
                }
                return ['pg_catalog', $typeName, $isArray];
            }
        }

        $position   = \strspn($name, BaseConverter::WHITESPACE);
        $length     = \strlen($name);
        $typeName   = null;
        $schema     = null;
        $isArray    = false;
        $identifier = true;

        while ($position < $length) {
            if ('[' === $name[$position]) {
                if (
                    !$typeName || $isArray || $identifier
                    || !\preg_match('/\[\s*]$/A', $name, $m, 0, $position)
                ) {
                    throw new InvalidArgumentException("Invalid array specification in type name '$name'");
                }
                $isArray     = true;
                $position   += \strlen($m[0]);
                $identifier  = false;

            } elseif ('.' === $name[$position]) {
                /** @psalm-suppress TypeDoesNotContainType */
                if ($schema || !$typeName || $identifier) {
                    throw new InvalidArgumentException("Extra dots in type name '$name'");
                }
                [$schema, $typeName] = [$typeName, null];
                $position++;
                $identifier = true;

            } elseif ('"' === $name[$position]) {
                if (
                    !\preg_match('/"((?>[^"]+|"")*)"/A', $name, $m, 0, $position)
                    || !\strlen($m[1])
                ) {
                    throw new InvalidArgumentException("Invalid double-quoted string in type name '$name'");
                } elseif (!$identifier) {
                    throw new InvalidArgumentException(
                        "Unexpected double-quoted string '$m[0]' in type name '$name'"
                    );
                }
                $typeName    = \strtr($m[1], ['""' => '"']);
                $position   += \strlen($m[0]);
                $identifier  = false;

            } elseif (\preg_match('/[A-Za-z\x80-\xff_][A-Za-z\x80-\xff_0-9$]*/A', $name, $m, 0, $position)) {
                if (!$identifier) {
                    throw new InvalidArgumentException("Unexpected identifier '$m[0]' in type name '$name'");
                }
                $typeName    = \strtolower($m[0]);
                $position   += \strlen($m[0]);
                $identifier  = false;

            } else {
                throw new InvalidArgumentException("Unexpected symbol '$name[$position]' in type name '$name'");
            }

            $position += \strspn($name, BaseConverter::WHITESPACE, $position);
        }

        if (null === $typeName) {
            throw new InvalidArgumentException("Missing type name in '$name'");
        }
        return [$schema, $typeName, $isArray];
    }

    /**
     * Returns an instance of converter explicitly registered for a given type
     *
     * @param string      $typeName   type name (as passed to registerConverter())
     * @param string|null $schemaName schema name (only required if converters for the same
     *                                type name were registered for different schemas)
     * @throws InvalidArgumentException
     */
    private function getRegisteredConverterInstance(string $typeName, ?string $schemaName = null): ?TypeConverter
    {
        if (
            empty($this->types[$typeName])
            || null !== $schemaName && !isset($this->types[$typeName][$schemaName])
        ) {
            return null;
        }
        if (null === $schemaName) {
            if (1 < \count($this->types[$typeName])) {
                throw new InvalidArgumentException(\sprintf(
                    '%s: Converters for type "%s" exist for schemas: %s. Qualified name required.',
                    __METHOD__,
                    $typeName,
                    \implode(', ', \array_keys($this->types[$typeName]))
                ));
            }
            $schemaName = \array_key_first($this->types[$typeName]);
        }
        if (empty($this->converters[$typeName][$schemaName])) {
            if ($this->types[$typeName][$schemaName] instanceof TypeConverter) {
                $converter = clone $this->types[$typeName][$schemaName];

            } elseif (\is_callable($this->types[$typeName][$schemaName])) {
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
     * Name can be either a "known" base type name from $types property,
     * 'foo[]' for an array of base type 'foo', or a database-specific name
     * (e.g. for composite type)
     *
     * @throws InvalidArgumentException
     */
    private function getConverterForTypeName(string $name): TypeConverter
    {
        if (isset($this->parsedNames[$name])) {
            [$typeName, $schemaName, $isArray] = $this->parsedNames[$name];
        } else {
            if (!\preg_match('/^([A-Za-z\x80-\xff_][A-Za-z\x80-\xff_0-9$]*)(\[])?$/', $name, $m)) {
                [$schemaName, $typeName, $isArray] = $this->parseTypeName($name);

            } else {
                $schemaName = null;
                $isArray    = !empty($m[2]);
                $typeName   = \strtolower($m[1]);
                if (isset(self::SIMPLE_ALIASES[$typeName])) {
                    $typeName = self::SIMPLE_ALIASES[$typeName];
                }
            }

            $this->parsedNames[$name] = [$typeName, $schemaName, $isArray];
        }

        return $isArray
            ? new containers\ArrayConverter($this->getConverterForQualifiedName($typeName, $schemaName))
            : $this->getConverterForQualifiedName($typeName, $schemaName);
    }

    /**
     * Returns type converter for separately supplied type and schema names
     *
     * @throws InvalidArgumentException
     */
    public function getConverterForQualifiedName(
        string $typeName,
        ?string $schemaName = null/*,
        bool $isArray = false*/
    ): TypeConverter {
        if (\func_num_args() <= 2) {
            $isArray = false;
        } else {
            @\trigger_error(\sprintf(
                '$isArray argument for %s() method is deprecated since release 3.1.0',
                __METHOD__
            ), \E_USER_DEPRECATED);
            $isArray = func_get_arg(2);
        }
        if (null === $converter = $this->getRegisteredConverterInstance($typeName, $schemaName)) {
            $mapper = $this->getOIDMapper();
            $oid    = $mapper->findOIDForTypeName($typeName, $schemaName);
            if (!$mapper->isBaseTypeOID($oid)) {
                $converter = $this->getConverterForTypeOID($oid);
            } else {
                // a converter required by name is required explicitly -> exception if not found
                throw new InvalidArgumentException(\sprintf(
                    '%s: no converter registered for base type %s',
                    __METHOD__,
                    InvalidArgumentException::formatQualifiedName($typeName, $schemaName)
                ));
            }
        }

        return $isArray ? new containers\ArrayConverter($converter) : $converter;
    }
}
