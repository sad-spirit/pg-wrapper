.. _internals-oids:

=============================
Internals: Handling type OIDs
=============================

``DefaultTypeConverterFactory`` uses an implementation of ``TypeOIDMapper`` interface internally. It is required for
``getConverterForTypeOID()`` method to create a proper converter for a result field and is used in other methods
to create converters for custom complex types.

The only parts that are relevant to the user of the package are methods to control caching of composite types
structure, defined in ``CachedTypeOIDMapper`` and described below.

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
    metadata lookups from database on each page request.

``CachedTypeOIDMapper`` is pre-populated with info on PostgreSQL's built-in data types, thus it is usable
even without a configured connection. There will also be no need to query database for type metadata if only
the standard types are used.

If, however, the database has some custom types (``ENUM``\ s count), then the class will have to load type info
from the database and / or cache.


.. warning::

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

.. _internals-oids-explanation:

Why use OIDs and not type names directly?
=========================================

A valid question is why we need ``TypeOIDMapper`` in the first place when pgsql extension provides
`pg_field_type() <https://www.php.net/manual/en/function.pg-field-type.php>`__ that returns the type name
for the result column? Or when PDO has
`PDOStatement::getColumnMeta() <https://www.php.net/manual/en/pdostatement.getcolumnmeta.php>`__?

Result metadata in Postgres contains type OIDs for result columns and these are returned by
`PQftype function of client library <https://www.postgresql.org/docs/current/libpq-exec.html#LIBPQ-PQFTYPE>`__.
PHP's `pg_field_type_oid() <https://www.php.net/manual/en/function.pg-field-type-oid.php>`__ is a thin wrapper
around that function.

Type name data should be fetched separately, quoting documentation of ``PQftype()``:

    You can query the system table ``pg_type`` to obtain the names and properties of the various data types.

Well, PHP's ``pg_field_type()`` does exactly that, it just selects all rows of ``pg_catalog.pg_type``
on the first call and later searches the fetched data for type OIDs.
However, it only fetches the unqualified type name: no schema name, no properties.

``CachedTypeOIDMapper`` does mostly the same, but fetches more info and allows caching and reusing
the type data between requests.

PDO is in a league of its own: it has an extremely inefficient way of working with column metadata. This starts
with the API design, where ``PDOStatement::getColumnMeta()`` tries to return all the column's
metadata at once with no means to request e.g. only ``pgsql:oid`` field. For Postgres this means running *two* queries
to populate ``table`` and ``native_type`` fields. Additionally, ``PDO_pgsql`` driver doesn't cache the metadata,
resulting in potentially two metadata queries *for every column* in *every result*.

To be fair, some of the most common built-in types
`do not require a query <https://github.com/php/php-src/blob/16c9652f2729325dbd31c1d92578e2d41d50ef0c/ext/pdo_pgsql/pgsql_statement.c#L759>`__
for ``native_type`` in ``getColumnMeta()``, but a query for ``table``
`will always be run <https://github.com/php/php-src/blob/16c9652f2729325dbd31c1d92578e2d41d50ef0c/ext/pdo_pgsql/pgsql_statement.c#L703>`__.
