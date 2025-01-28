.. _converter-factories:

========================
Type converter factories
========================

Factories are used to simplify passing type information to query execution methods, consider

.. code-block:: php
    :caption: query with type specifications

    $result = $connection->executeParams(
        'select row(foo_id, foo_added) from foo where bar = any($1::integer[])',
        [[1, 2, 3]],
        ['integer[]'],
        [['id' => 'integer', 'added' => 'timestamptz']]
    );

vs

.. code-block:: php
    :caption: query with ``TypeConverter`` instances

    $result = $connection->executeParams(
        'select * from foo where bar = any($1::integer[])',
        [[1, 2, 3]],
        [new ArrayConverter(new IntegerConverter())],
        [new CompositeConverter(['id' => new IntegerConverter(), 'added' => new TimeStampTzConverter()])]
    );

and to transparently convert the result fields using metadata returned by
`pg_field_type_oid() <https://www.php.net/manual/en/function.pg-field-type-oid.php>`__.

In the "wrapper" part of the package factory methods are called by ``Connection`` and ``PreparedStatement`` classes
when converting query parameters and by ``Result`` when converting query results.
The only methods that should be called directly are those used to set up
:ref:`custom types conversion <converter-factories-setup>`,
most probably for :ref:`enum types <converter-factories-enum>` and the like.

Common interface
================

Classes that create type converters implement the following interface

.. code-block:: php

    namespace sad_spirit\pg_wrapper;

    use sad_spirit\pg_wrapper\converters\ConnectionAware;

    interface TypeConverterFactory extends ConnectionAware
    {
        public function getConverterForTypeSpecification(mixed $type): TypeConverter;
        public function getConverterForTypeOID(int|string $oid): TypeConverter;
        public function getConverterForPHPValue(mixed $value): TypeConverter;
    }

``getConverterForTypeSpecification()``
    This method returns a converter based on manually provided type specification (commonly, a type name).
    It should throw an exception if a matching converter cannot be found as this is most probably an user error.

    Values accepted as specification are implementation-specific. Any implementation should, however, accept
    an instance of ``TypeConverter`` and update it with the ``Connection`` instance
    if it implements ``ConnectionAware``.

``getConverterForTypeOID()``
    Returns a converter for the type with the given OID. It expects an OID (internal Postgres object identifier)
    that is a primary key for some row of system ``pg_type`` table.

    The method is mainly used by ``Result`` to find converters for returned columns. It should not throw an exception
    if a converter is not found, usually returning an instance of ``StubConverter`` in that case.

``getConverterForPHPValue()``
    Tries to return a converter based on type/class of its argument.

    This is used by query execution methods if type specification was not given for a query parameter. It should
    throw an exception if the argument type is ambiguous (e.g. PHP array) or its class is not known.

As the interface extends ``ConnectionAware`` it is possible to specify the ``Connection`` this factory works with.
Usually the factory will be able to perform *some* conversions without ``Connection`` specified, but definitely
not those of the custom database types.

The package contains two implementations of ``TypeConverterFactory``: ``converters\DefaultTypeConverterFactory``
and ``converters\StubTypeConverterFactory``

``StubTypeConverterFactory``
============================

``getConverterForTypeSpecification()`` method of this class returns

- ``$type`` argument, if it is an instance of ``TypeConverter``. It will be configured with current ``Connection``
  if it implements ``ConnectionAware``.
- An instance of ``converters\StubConverter`` if ``$type`` is anything else.

Its ``getConverterForTypeOID()`` and ``getConverterForPHPValue()`` also return ``converters\StubConverter``.

This can be used to essentially disable type conversion, making package behave like stock ``pgsql`` extension.

.. _converter-factories-default:

``DefaultTypeConverterFactory``
===============================

This is the default implementation of ``TypeConverterFactory`` interface. Its instance is automatically added
to a ``Connection`` object unless ``setTypeConverterFactory()`` is explicitly used.

Type specifications accepted
----------------------------

``getConverterForTypeSpecification()`` method accepts the following as its ``$type`` argument:

- Type name as a string. A minimal parser is implemented, so schema-qualified names like ``pg_catalog.int4``,
  double-quoted identifiers like ``"CamelCaseType"``, SQL standard names like ``CHARACTER VARYING`` will be understood.

  Array types can be specified with square brackets as ``typename[]``.

- ``TypeConverter`` instance. Its properties will be updated from current ``Connection`` object if needed
  (e.g. date and time converters will use ``DateStyle`` setting of connected database).
- Composite type specification as an array
  ``'column' => 'column type specification'``

.. _converter-factories-setup:

Additional methods
------------------

``registerConverter(string|callable|TypeConverter $converter, string|array $type, string $schema = 'pg_catalog'): void``

  Registers a new converter for a base type. ``$converter`` argument is either a name of the class implementing
  ``TypeConverter``, a callable returning ``TypeConverter`` instance or an object implementing ``TypeConverter``
  that will be used as a prototype for cloning.

``registerClassMapping(string $className, string $type, string $schema = 'pg_catalog'): void``

  Registers a mapping from PHP class to a database type name. If you pass an instance of this class to
  ``getConverterForPHPValue()`` it will return a converter for this type. This is used in query execution methods
  to convert query parameters that didn't have their types specified explicitly.

Note that it is only needed to register converters for base types, proper converters for arrays / composites / ranges
over these base types will be built automatically:

.. code-block:: php

   $factory->registerConverter('BlahConverter', 'blah', 'blah');
   $factory->getConverter('blah.blah[]');

will return

.. code-block:: php

   new ArrayConverter(new BlahConverter());

``DefaultTypeConverterFactory`` also implements the ``TypeOIDMapperAware`` interface

.. code-block:: php

    namespace sad_spirit\pg_wrapper\converters;

    interface TypeOIDMapperAware
    {
        public function setOIDMapper(TypeOIDMapper $mapper): void;
        public function getOIDMapper(): TypeOIDMapper;
    }

An implementation of ``TypeOIDMapper`` is used, as its name implies, to map type OIDs to type names and is required
mostly for ``getConverterForTypeOID()`` method.

Type names supported out of the box
-----------------------------------

The following is a list of base type names and names of built-in range types understood by
``DefaultTypeConverterFactory``, those can be converted without setting up the ``Connection``. This allows
using the factory separately e.g. with PDO.

Note the following when reading the table:

- Type names in ``lowercase`` are PostgreSQL's internal, corresponding to rows in
  ``pg_catalog.pg_type``. Those in ``UPPERCASE`` are their SQL standard synonyms.
- ``sad_spirit\pg_wrapper\converters`` namespace prefix is assumed for all
  :ref:`converter class names <converters-implementations>`.
- ``sad_spirit\pg_wrapper\types`` namespace prefix is assumed for all
  :ref:`complex type class names <complex-types>` that do not start with a backslash.
- "Compatible ``array``" is an array that will be accepted by ``createFromArray()`` method of type's class.

.. table:: Base types

    +---------------------------------+---------------------------------------+-----------------------------+---------------------------+
    | Type names                      | ``TypeConverter`` implementation      | Non-null PHP value returned | Non-null PHP values       |
    |                                 |                                       |                             | accepted                  |
    +=================================+=======================================+=============================+===========================+
    | ``bool``,                       | ``BooleanConverter``                  | ``bool``                    | anything, PHP values      |
    | ``BOOLEAN``                     |                                       |                             | evaluating to ``false``   |
    |                                 |                                       |                             | and strings ``'false'``   |
    |                                 |                                       |                             | and ``'f'`` are converted |
    |                                 |                                       |                             | to ``'f'``, everything    |
    |                                 |                                       |                             | else to ``'t'``           |
    +---------------------------------+---------------------------------------+-----------------------------+---------------------------+
    | ``bytea``                       | ``ByteaConverter``                    | ``string`` (binary)         | ``string`` (binary)       |
    +---------------------------------+---------------------------------------+-----------------------------+---------------------------+
    | ``cstring``, ``text``,          | ``StringConverter``                   | ``string``                  | ``string``                |
    | ``char``, ``varchar``,          |                                       |                             |                           |
    | ``bpchar``, ``name``,           |                                       |                             |                           |
    | ``CHARACTER``, ``NCHAR``,       |                                       |                             |                           |
    | ``CHAR VARYING``,               |                                       |                             |                           |
    | ``CHARACTER VARYING``,          |                                       |                             |                           |
    | ``BIT VARYING``,                |                                       |                             |                           |
    | ``NCNAR VARYING``,              |                                       |                             |                           |
    | ``NATIONAL CHAR VARYING``,      |                                       |                             |                           |
    | ``NATIONAL CHARACTER VARYING``  |                                       |                             |                           |
    |                                 |                                       |                             |                           |
    +---------------------------------+---------------------------------------+-----------------------------+---------------------------+
    | ``oid``, ``cid``,               | ``IntegerConverter``                  | ``int``, ``numeric-string`` | numeric values            |
    | ``xid``, ``int2``,              |                                       | if integer is out of range  |                           |
    | ``int4``, ``int8``,             |                                       | for PHP (e.g. ``int8``      |                           |
    | ``INT``, ``INTEGER``,           |                                       | type on 32-bit PHP)         |                           |
    | ``SMALLINT``,                   |                                       |                             |                           |
    | ``BIGINT``                      |                                       |                             |                           |
    +---------------------------------+---------------------------------------+-----------------------------+                           |
    | ``numeric``, ``money``          | ``NumericConverter``                  | ``string``, to prevent      |                           |
    | ``DECIMAL``, ``DEC``            |                                       | loss of precision           |                           |
    +---------------------------------+---------------------------------------+-----------------------------+                           |
    | ``float4``, ``float8``          | ``FloatConverter``                    | ``float``                   |                           |
    | ``REAL``, ``FLOAT``,            |                                       |                             |                           |
    | ``DOUBLE PRECISION``            |                                       |                             |                           |
    +---------------------------------+---------------------------------------+-----------------------------+---------------------------+
    | ``json``, ``jsonb``             | ``JsonConverter``                     | usually an ``array``        | anything                  |
    |                                 |                                       |                             | ``json_encode()``         |
    |                                 |                                       |                             | can handle                |
    +---------------------------------+---------------------------------------+-----------------------------+---------------------------+
    | ``date``                        | ``datetime\DateConverter``            | instance of                 | - instance of             |
    +---------------------------------+---------------------------------------+ ``\DateTimeImmutable``      |   ``\DateTimeInterface``  |
    | ``time``,                       | ``datetime\TimeConverter``            |                             | - ``string`` (passed as   |
    | ``TIME WITHOUT TIME ZONE``      |                                       |                             |   is)                     |
    |                                 |                                       |                             | - ``int`` (treated as     |
    +---------------------------------+---------------------------------------+                             |   UNIX timestamp)         |
    | ``timetz``,                     | ``datetime\TimeTzConverter``          |                             |                           |
    | ``TIME WITH TIME ZONE``         |                                       |                             |                           |
    +---------------------------------+---------------------------------------+                             |                           |
    | ``timestamp``,                  | ``datetime\TimeStampConverter``       |                             |                           |
    | ``TIMESTAMP WITHOUT TIME ZONE`` |                                       |                             |                           |
    +---------------------------------+---------------------------------------+                             |                           |
    | ``timestamptz``,                | ``datetime\TimeStampTzConverter``     |                             |                           |
    | ``TIMESTAMP WITH TIME ZONE``    |                                       |                             |                           |
    |                                 |                                       |                             |                           |
    +---------------------------------+---------------------------------------+-----------------------------+---------------------------+
    | ``interval``                    | ``datetime\IntervalConverter``        | instance of                 | - instance of             |
    |                                 |                                       | ``\DateInterval``           |   ``\DateInterval``       |
    |                                 |                                       |                             | - ``int`` / ``float``     |
    |                                 |                                       |                             |   (treated as number of   |
    |                                 |                                       |                             |   seconds)                |
    |                                 |                                       |                             | - ``string`` (passed as   |
    |                                 |                                       |                             |   is)                     |
    +---------------------------------+---------------------------------------+-----------------------------+---------------------------+
    | ``json``, ``jsonb``             | ``JsonConverter``                     | usually an ``array``        | anything                  |
    |                                 |                                       |                             | ``json_encode()``         |
    |                                 |                                       |                             | can handle                |
    |                                 |                                       |                             |                           |
    |                                 |                                       |                             |                           |
    +---------------------------------+---------------------------------------+-----------------------------+---------------------------+
    | ``box``                         | ``geometric\BoxConverter``            | instance of ``Box``         | instance of ``Box``       |
    |                                 |                                       |                             | or compatible ``array``   |
    +---------------------------------+---------------------------------------+-----------------------------+---------------------------+
    | ``circle``                      | ``geometric\CircleConverter``         | instance of ``Circle``      | instance of ``Circle``    |
    |                                 |                                       |                             | or compatible ``array``   |
    +---------------------------------+---------------------------------------+-----------------------------+---------------------------+
    | ``line``                        | ``geometric\LineConverter``           | instance of ``Line``        | instance of ``Line``      |
    |                                 |                                       |                             | or compatible ``array``   |
    +---------------------------------+---------------------------------------+-----------------------------+---------------------------+
    | ``lseg``                        | ``geometric\LSegConverter``           | instance of ``LineSegment`` | instance of               |
    |                                 |                                       |                             | ``LineSegment``           |
    |                                 |                                       |                             | or compatible ``array``   |
    +---------------------------------+---------------------------------------+-----------------------------+---------------------------+
    | ``path``                        | ``geometric\PathConverter``           | instance of ``Path``        | instance of ``Path``      |
    |                                 |                                       |                             | or compatible ``array``   |
    +---------------------------------+---------------------------------------+-----------------------------+---------------------------+
    | ``point``                       | ``geometric\PointConverter``          | instance of ``Point``       | instance of ``Point``     |
    |                                 |                                       |                             | or compatible ``array``   |
    +---------------------------------+---------------------------------------+-----------------------------+---------------------------+
    | ``polygon``                     | ``geometric\PolygonConverter``        | instance of ``Polygon``     | instance of ``Polygon``   |
    |                                 |                                       |                             | or compatible ``array``   |
    +---------------------------------+---------------------------------------+-----------------------------+---------------------------+
    | ``tid``                         | ``TidConverter``                      | instance of ``Tid``         | instance of ``Tid``       |
    |                                 |                                       |                             | or compatible ``array``   |
    +---------------------------------+---------------------------------------+-----------------------------+---------------------------+
    | ``hstore``                      | ``container\HstoreConverter``         | ``array<string,?string>``   | ``array`` or ``object``   |
    | (from ``contrib/hstore``        |                                       |                             |                           |
    | extension)                      |                                       |                             |                           |
    +---------------------------------+---------------------------------------+-----------------------------+---------------------------+
    | ``int2vector``, ``oidvector``   | ``containers\IntegerVectorConverter`` | ``list<int|numeric-string>``| single-dimension ``array``|
    |                                 |                                       |                             | of numeric values         |
    +---------------------------------+---------------------------------------+-----------------------------+---------------------------+

.. table:: Built-in range and multirange types

    +---------------------------------+-------------------------------------+-----------------------------+---------------------------+
    | Type names                      | ``TypeConverter`` implementation    | Non-null PHP value returned | Non-null PHP values       |
    |                                 |                                     |                             | accepted                  |
    +=================================+=====================================+=============================+===========================+
    | ``int4range``, ``int8range``    | ``\containers\RangeConverter``      | instance of                 | instance of               |
    |                                 | with ``IntegerConverter``           | ``NumericRange``            | ``NumericRange``          |
    |                                 |                                     |                             | or compatible ``array``   |
    +---------------------------------+-------------------------------------+                             |                           |
    | ``numrange``                    | ``\containers\RangeConverter``      |                             |                           |
    |                                 | with ``NumericConverter``           |                             |                           |
    +---------------------------------+-------------------------------------+-----------------------------+---------------------------+
    | ``daterange``                   | ``\containers\RangeConverter``      | instance of                 | instance of               |
    |                                 | with                                | ``DateTimeRange``           | ``DateTimeRange``         |
    |                                 | ``datetime\DateConverter``          |                             | or compatible ``array``   |
    +---------------------------------+-------------------------------------+                             |                           |
    | ``tsrange``                     | ``\containers\RangeConverter``      |                             |                           |
    |                                 | with                                |                             |                           |
    |                                 | ``datetime\TimeStampConverter``     |                             |                           |
    +---------------------------------+-------------------------------------+                             |                           |
    | ``tstzrange``                   | ``\containers\RangeConverter``      |                             |                           |
    |                                 | with                                |                             |                           |
    |                                 | ``datetime\TimeStampTzConverter``   |                             |                           |
    +---------------------------------+-------------------------------------+-----------------------------+---------------------------+
    | ``int4multirange``,             | ``\containers\MultiRangeConverter`` | instance of                 | instance of               |
    | ``int8multirange``              | with ``IntegerConverter``           | ``NumericMultiRange``       | ``NumericMultiRange``     |
    |                                 |                                     |                             | or compatible ``array``   |
    +---------------------------------+-------------------------------------+                             |                           |
    | ``nummultirange``               | ``\containers\MultiRangeConverter`` |                             |                           |
    |                                 | with ``NumericConverter``           |                             |                           |
    +---------------------------------+-------------------------------------+-----------------------------+---------------------------+
    | ``datemultirange``              | ``\containers\MultiRangeConverter`` | instance of                 | instance of               |
    |                                 | with ``datetime\DateConverter``     | ``DateTimeMultiRange``      | ``DateTimeMultiRange``    |
    |                                 |                                     |                             | or compatible ``array``   |
    +---------------------------------+-------------------------------------+                             |                           |
    | ``tsmultirange``                | ``\containers\MultiRangeConverter`` |                             |                           |
    |                                 | with                                |                             |                           |
    |                                 | ``datetime\TimeStampConverter``     |                             |                           |
    +---------------------------------+-------------------------------------+                             |                           |
    | ``tstzmultirange``              | ``\containers\MultiRangeConverter`` |                             |                           |
    |                                 | with                                |                             |                           |
    |                                 | ``datetime\TimeStampTzConverter``   |                             |                           |
    +---------------------------------+-------------------------------------+-----------------------------+---------------------------+

.. _converter-factories-classes:

Classes mapped to database types
--------------------------------

Passing instances of the below classes (``sad_spirit\pg_wrapper\types`` namespace prefix is assumed for all names
that do not start with a backslash) as query parameters does not require specifying parameter types.
Converters for database types in the second column will be used.

+-----------------------------------------------+--------------------------------------------------+
| Class name                                    | Database type                                    |
+===============================================+==================================================+
| ``\DateTimeInterface``                        | ``timestamptz``                                  |
+-----------------------------------------------+--------------------------------------------------+
| ``\DateInterval``                             | ``interval``                                     |
+-----------------------------------------------+--------------------------------------------------+
| ``Box``                                       | ``box``                                          |
+-----------------------------------------------+--------------------------------------------------+
| ``Circle``                                    | ``circle``                                       |
+-----------------------------------------------+--------------------------------------------------+
| ``Line``                                      | ``line``                                         |
+-----------------------------------------------+--------------------------------------------------+
| ``LineSegment``                               | ``lseg``                                         |
+-----------------------------------------------+--------------------------------------------------+
| ``Path``                                      | ``path``                                         |
+-----------------------------------------------+--------------------------------------------------+
| ``Point``                                     | ``point``                                        |
+-----------------------------------------------+--------------------------------------------------+
| ``Polygon``                                   | ``polygon``                                      |
+-----------------------------------------------+--------------------------------------------------+
| ``DateTimeRange``                             | ``tstzrange``                                    |
+-----------------------------------------------+--------------------------------------------------+
| ``DateTimeMultiRange``                        | ``tstzmultirange``                               |
+-----------------------------------------------+--------------------------------------------------+
| ``NumericRange``                              | ``numrange``                                     |
+-----------------------------------------------+--------------------------------------------------+
| ``NumericMultiRange``                         | ``nummultirange``                                |
+-----------------------------------------------+--------------------------------------------------+
| ``Tid``                                       | ``tid``                                          |
+-----------------------------------------------+--------------------------------------------------+

.. _converter-factories-enum:

Converting enums
----------------

It is not strictly necessary to convert values of Postgres ``ENUM`` types: those are returned as strings and
string values are accepted for them as parameters.

However, if one wants a mapping between Postgres enum type

.. code-block:: postgres

    CREATE TYPE meta.syntactic AS ENUM ('foo', 'bar', 'baz');

and PHP's string-backed counterpart

.. code-block:: php

    enum MetaSyntactic: string
    {
        case FOO = 'foo';
        case BAR = 'bar';
        case BAZ = 'baz';
    }

then setting up the factory in this way

.. code-block:: php

    use sad_spirit\pg_wrapper\converters\EnumConverter;

    $factory->registerConverter(static function () {
        return new EnumConverter(MetaSyntactic::class);
    }, 'syntactic', 'meta');
    $factory->registerClassMapping(MetaSyntactic::class, 'syntactic', 'meta');

will allow both receiving values of ``meta.syntactic`` type as cases of ``MetaSyntactic`` and passing these cases
as query parameters without the need to specify types.


``TypeOIDMapper`` interface and its implementation
==================================================

This interface defines methods for

- Converting type OIDs to type names and back;
- Checking whether the given type OID belongs to some type category.

.. code-block:: php

    namespace sad_spirit\pg_wrapper\converters;

    interface TypeOIDMapper
    {
        public function findOIDForTypeName(string $typeName, ?string $schemaName = null): int|string;
        public function findTypeNameForOID(int|string $oid): array;

        public function isBaseTypeOID(int|string $oid): bool;
        public function isArrayTypeOID(int|string $oid, int|string|null &$baseTypeOid = null): bool;
        public function isCompositeTypeOID(int|string $oid, array|null &$members = null): bool;
        public function isDomainTypeOID(int|string $oid, int|string|null &$baseTypeOid = null): bool;
        public function isRangeTypeOID(int|string $oid, int|string|null &$baseTypeOid = null): bool;
        public function isMultiRangeTypeOID(int|string $oid, int|string|null &$baseTypeOid = null): bool;
    }

``findOIDForTypeName()`` / ``findTypeNameForOID()``
    Convert OIDs to type names and back. Those should throw ``InvalidArgumentException`` if the relevant data
    can not be found or if the input is ambiguous (unqualified ``$typeName`` appearing in several schemas).

``isBaseTypeOID()``
    Returns ``true`` if type OID does not belong to any of the special categories, ``false`` otherwise.

``isArrayTypeOID()``, ``isDomainTypeOID()``, ``isRangeTypeOID()``, ``isMultiRangeTypeOID()``
    These check whether the type OID belongs to the relevant category, if that is the case then
    ``$baseTypeOid`` will be set to the OID of the base type.

``isCompositeTypeOID()``
    Checks whether the type OID represents a composite type. If that is the case, ``$members`` will be set
    to an array ``'field name' => field type OID``.

``CachedTypeOIDMapper``
-----------------------

This is the default implementation of ``TypeOIDMapper``, an instance of this will be added to
``DefaultTypeConverterFactory`` unless ``setOIDMapper()`` is called explicitly.

It implements ``ConnectionAware`` and will use the provided ``Connection`` instance to load types data
from the connected database. It will also use ``Connection``\ 's metadata cache, if that was provided via
``setMetadataCache()``, to store types data.

.. note::
    Using some sort of cache is highly recommended in production to prevent
    database lookups on each page request.

``CachedTypeOIDMapper`` is pre-populated with info on PostgreSQL's built-in data types, thus it is usable
even without a configured connection. There will also be no need to query database for type metadata if only
the standard types are used.

If, however, the database has some custom types (``ENUM``\ s count), then the class will have to load type info
from the database and / or cache.


.. note::

    While the class is smart enough to reload metadata from database when ``OID`` is not found in the cached data
    (i.e. a new type was added after cache saved) it is unable to handle changes in composite type structure,
    so either disable caching of that or invalidate the cache manually.

These additional public methods control caching of composite types

``setCompositeTypesCaching(bool $caching): $this``
    Sets whether structure of composite (row) types will be stored in the cache. If the cached
    list of columns is used to convert the composite value with different columns the conversion will obviously fail,
    so that should be set to ``false`` if you:

    - Use composite types in the application;
    - Expect changes to those types.

``getCompositeTypesCaching(): bool``
    Returns whether composite types' structure is cached

Why use OIDs and not type names directly?
-----------------------------------------

Result metadata in Postgres contains type OIDs for result columns and these are returned by
`PQftype function of client library <https://www.postgresql.org/docs/17/libpq-exec.html#LIBPQ-PQFTYPE>`__.
PHP's `pg_field_type_oid() <https://www.php.net/manual/en/function.pg-field-type-oid.php>`__ is a thin wrapper
around that function.

Type name data should be fetched separately, quoting documentation of ``PQftype()``:

    You can query the system table ``pg_type`` to obtain the names and properties of the various data types.

PHP's `pg_field_type() <https://www.php.net/manual/en/function.pg-field-type.php>`__ does exactly that,
it just selects all rows of ``pg_catalog.pg_type`` on the first call and later searches the fetched data for
type OIDs. However, it only fetches the unqualified type name: no schema name, no properties.

``CachedTypeOIDMapper`` does mostly the same, but fetches more info and allows caching and reusing
the type data between requests.
