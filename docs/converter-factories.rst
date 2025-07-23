.. _converter-factories:

========================
Type converter factories
========================

Factories are used to create type converters for the result fields using metadata provided by
`pg_field_type_oid() <https://www.php.net/manual/en/function.pg-field-type-oid.php>`__
and to simplify passing type information to query execution methods. Consider passing type specifications

.. code-block:: php

    $result = $connection->executeParams(
        'select row(foo_id, foo_added) from foo where bar = any($1::integer[])',
        [[1, 2, 3]],
        ['integer[]'],
        [['id' => 'integer', 'added' => 'timestamptz']]
    );

vs creating converters manually

.. code-block:: php

    $result = $connection->executeParams(
        'select * from foo where bar = any($1::integer[])',
        [[1, 2, 3]],
        [new ArrayConverter(new IntegerConverter())],
        [new CompositeConverter(['id' => new IntegerConverter(), 'added' => new TimeStampTzConverter()])]
    );

In the "wrapper" part of the package factory methods are called by ``Connection`` and ``PreparedStatement`` classes
when converting query parameters and by ``Result`` when converting query results.
The only methods that will be called directly are those used to set up
:ref:`custom types conversion <converter-factories-setup>`,
most probably for :ref:`enum types <converter-factories-enum>` and the like.

Common interfaces
=================

``TypeConverterFactory``
------------------------

All classes that create type converters implement the following interface

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
    This method returns a converter based on manually provided type specification (commonly,
    :ref:`a type name <converter-factories-names>`).
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

``TypeOIDMapperAware``
----------------------

This interface should be implemented by classes that use an instance of ``TypeOIDMapper``:

.. code-block:: php

    namespace sad_spirit\pg_wrapper\converters;

    interface TypeOIDMapperAware
    {
        public function setOIDMapper(TypeOIDMapper $mapper): void;
        public function getOIDMapper(): TypeOIDMapper;
    }

An implementation of ``TypeOIDMapper`` is used, as its name implies, to map type OIDs to type names and is required
mostly for ``getConverterForTypeOID()`` method.

.. _converter-factories-setup:

``ConfigurableTypeConverterFactory``
------------------------------------

This interface was introduced in release 3.1 to be used in type hints instead of ``DefaultTypeConverterFactory``.
It defines additional methods to register new type converters and type classes with the factory

.. code-block:: php

    namespace sad_spirit\pg_wrapper\converters;

    use sad_spirit\pg_wrapper\TypeConverter;
    use sad_spirit\pg_wrapper\TypeConverterFactory;

    interface ConfigurableTypeConverterFactory extends TypeConverterFactory, TypeOIDMapperAware
    {
        public function registerConverter(
            callable|TypeConverter|class-string<TypeConverter> $converter,
            string[]|string $type,
            string $schema = 'pg_catalog'
        ): void;
        public function registerClassMapping(class-string $className, string $type, string $schema = 'pg_catalog'): void;
        public function getConverterForQualifiedName(string $typeName, ?string $schemaName = null): TypeConverter;
    }

``registerConverter()``
    Registers a converter for a named type. When a converter is requested for the given type name via
    ``getConverterForQualifiedName()``, ``$converter`` will be used to create the return value.

    ``$converter`` can be either of

     * ``TypeConverter`` instance, a clone of that will be returned by ``getConverterForQualifiedName()``;
     * a callable returning an instance of ``TypeConverter``;
     * Name of the class implementing ``TypeConverter``.

``registerClassMapping()``
    Registers a mapping between PHP class and database type name. When an instance of the given class will be provided
    to ``getConverterForPHPValue()`` a converter for the given database type will be returned.

``getConverterForQualifiedName()``
    Returns type converter for separately supplied type and schema names. If a converter for a base type is requested,
    and it was not registered via ``registerConverter()``, an exception will be thrown.

    This method was previously marked as ``@internal`` but now can be considered a part of the API.

Note that it is only needed to register converters for base types, proper converters for arrays / composites / ranges
over these base types will be built automatically:

.. code-block:: php

   $factory->registerConverter(BlahConverter::class, 'blah', 'blah');
   $factory->getConverter('blah.blah[]');

will return

.. code-block:: php

   new ArrayConverter(new BlahConverter());

Factory implementations
=======================

``StubTypeConverterFactory``
----------------------------

``getConverterForTypeOID()`` and ``getConverterForPHPValue()`` methods of this class return
an instance of ``converters\StubConverter``.

Its ``getConverterForTypeSpecification()`` method also returns ``converters\StubConverter`` if passed anything except
an implementation of ``TypeConverter`` as a ``$type`` argument. Otherwise it will return ``$type``,
configured with current ``Connection`` if it implements ``ConnectionAware``.

.. tip::
    This class can be used to effectively disable type conversion, making package behave like stock ``pgsql`` extension.

.. _converter-factories-default:

``DefaultTypeConverterFactory``
-------------------------------

This is an implementation of ``ConfigurableTypeConverterFactory`` interface. Its instance is automatically added
to a ``Connection`` object unless ``setTypeConverterFactory()`` is explicitly used.

``getConverterForTypeSpecification()`` method accepts the following as its ``$type`` argument:

- Type name as a string. A minimal parser is implemented, so schema-qualified names like ``pg_catalog.int4``,
  double-quoted identifiers like ``"CamelCaseType"``, SQL standard names like ``CHARACTER VARYING`` will be understood.

  Array types can be specified with square brackets as ``typename[]``.

  :ref:`The next chapter <converter-factories-names>` lists all type names that are supported by default.

- ``TypeConverter`` instance. Its properties will be updated from current ``Connection`` object if needed
  (e.g. date and time converters will use ``DateStyle`` setting of connected database).
- Composite type specification as an array
  ``['column' => column type specification, ...]``
- Array type specification as an array ``['' => base type specification]``, where base type may be anything
  other than an array, as those cannot be nested. This is mostly intended for specifying an array of composite type.

  Note the empty string used as an array key: an empty string cannot be a column name or alias in Postgres, so
  this is used to differentiate from composite type specification.
