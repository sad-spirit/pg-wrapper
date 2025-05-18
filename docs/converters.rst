.. _converters:

===============
Type converters
===============

Type converters are the classes that

- Parse string representations of database types returned by Postgres into native PHP types;
- Generate string representations from native PHP types that can be used as query parameters.

These classes are pretty low-level and it is generally not needed to manually instantiate them: this is handled by
an implementation of ``TypeConverterFactory``. In the "wrapper" part of the package, it is seldom needed to
use them directly at all: calling ``input()`` and ``output()`` is handled by ``Result`` and
``Connection`` / ``PreparedStatement`` classes, respectively.

``TypeConverter`` and related interfaces
========================================

The common interface for type converters defines the following methods

.. code-block:: php

    namespace sad_spirit\pg_wrapper;

    interface TypeConverter
    {
        public function output(mixed $value): ?string;
        public function input(?string $native): mixed;
        public function dimensions(): int;
    }

``output()``
    Returns a string representation of PHP variable suitable for PostgreSQL queries.
``input()``
    Converts a string received from PostgreSQL to PHP variable of the proper type.
``dimensions()``
    Returns number of array dimensions for PHP variable.

    This is needed for proper output of arrays by ``containers\ArrayConverter``.
    PostgreSQL does not enforce a number of dimensions for an array type, so
    an array passed to ``ArrayConverter::output()`` can legitimately have
    *any* number of dimensions. If the array's base type can also be
    represented by PHP array (e.g. geometric types), then number returned by
    ``dimensions()`` will help to understand where base type array ends and
    "outer" array begins.

If type converter's behaviour may change based on connection properties (e.g. connected server version)
then it should implement an additional interface

.. code-block:: php

    namespace sad_spirit\pg_wrapper\converters;

    use sad_spirit\pg_wrapper\Connection;

    interface ConnectionAware
    {
        public function setConnection(Connection $connection): void;
    }

The method accepts an instance of ``Connection`` and modifies the converter's behaviour based on its properties.

Date and time converters currently implement this interface to check server's ``DateStyle`` setting,
while numeric converters check whether non-decimal numeric literals will be accepted based on server version.

Another interface should be implemented by type converters for types having a non-comma array delimiter

.. code-block:: php

    namespace sad_spirit\pg_wrapper\converters;

    interface CustomArrayDelimiter
    {
        public function getArrayDelimiter(): string;
    }

The added method returns the symbol used to separate items of this type inside string representation of an array.

Currently only ``BoxConverter`` implements it as ``box`` is the only type in Postgres that uses a semicolon
as a delimiter within array literals.

.. _converters-implementations:

Type converter implementations
==============================

The below classes and interfaces, except builtin ones prefixed by a backslash,
belong to either ``\sad_spirit\pg_wrapper\converters`` or ``\sad_spirit\pg_wrapper\types``
(classes :ref:`representing complex types <complex-types>`) namespace. Those are omitted for brevity.

``StubConverter``
    This is the only implementation of ``TypeConverter`` that does not extend ``BaseConverter``.
    As its name implies, it *does not* perform conversion and is returned by

    - methods of ``StubTypeConverterFactory``,
    - ``DefaultTypeConverterFactory::getConverterForTypeOID()`` if a proper converter cannot be determined.

``BaseConverter``
    Abstract base class for converters, handles ``null`` values and provides several helper methods for parsing.

    ``BooleanConverter``
        Converts Postgres ``boolean`` values from / to PHP's ``bool``

    ``ByteaConverter``
        Converts binary data for ``bytea`` fields from / to string representation using ``hex`` encoding.

    ``EnumConverter``
        Converts values of Postgres ``ENUM`` type from / to cases of PHP's string-backed enum. The converter
        instance is configured with name of enum:

        .. code-block:: php

            $converter = new EnumConverter(SomeEnum::class);

    ``datetime\IntervalConverter``
        Postgres ``interval`` type <-> ``\DateInterval``

    ``JSONConverter``
        JSON string <-> Any PHP value that can be serialized.

    ``StringConverter``
        Postgres character types <-> PHP ``string``.
        This does not perform any conversion in ``input()``, casts PHP value to string in ``output()``.

    ``BaseNumericConverter``
        Abstract base class for numeric converters, implements ``ConnectionAware`` to check
        server version and allow non-decimal literals and separators when ``output()`` targets Postgres 16+

        ``NumericConverter``
            As ``numeric`` type of Postgres can have almost unlimited precision,
            ``input()`` keeps the value as string, only returning special "float" values for ``Infinity`` and ``NaN``

            ``FloatConverter``
                Postgres float types <-> PHP's ``float``

        ``IntegerConverter``
            Postgres integer types <-> PHP's ``int`` (the value may be left as string by ``input()``
            on 32bit builds of PHP, as ``int8`` values may overflow)

    ``datetime\BaseDateTimeConverter``
        Abstract base class for date and time converters, implements ``ConnectionAware``
        to check server's ``DateStyle`` setting and select proper format specification.
        Its ``input()`` method and those of its subclasses return instances of ``\DateTimeImmutable``.

        - ``datetime\DateConverter`` - converts ``date`` type
        - ``datetime\TimeConverter`` - converts ``time`` type
        - ``datetime\TimeTzConverter`` - converts ``timetz`` (``time with time zone``) type
        - ``datetime\TimeStampConverter`` - converts ``timestamp`` type
        - ``datetime\TimeStampTzConverter`` - converts ``timestamptz`` (``timestamp with time zone``) type

    ``ContainerConverter``
        Abstract base class for converters of "container" types: those are composed of multiple values of some "base"
        type. Usually the converter for "container" type uses the converter for "base" type to parse / generate
        parts of the value.

        ``containers\ArrayConverter``
            Postgres array <-> PHP array. The converter instance is configured by an instance of base type
            converter, e.g.

            .. code-block:: php

                $dateArrayConverter = new ArrayConverter(new DateConverter());
                // This will return an array of \DateTimeImmutable instances
                $dateArrayConverter->input($value);

        ``containers\CompositeConverter``
            Postgres composite (row) type <-> PHP array. The converter instance is configured by an array
            representing the composite type fields, thus for type defined like this

            .. code-block:: postgres

                create type foo as (id integer, name text, added timestamp with time zone);

            a converter may be created like this

            .. code-block:: php

                $fooConverter = new CompositeConverter([
                    'id'    => new IntegerConverter(),
                    'name'  => new StringConverter(),
                    'added' => new TimeStampTzConverter()
                ]);

        ``containers\HstoreConverter``
            ``hstore`` type from contrib/hstore Postgres extension <-> ``array<string, ?string>``.
            This type for storing key => value pairs was quite useful before JSON support was added to Postgres.

        ``containers\IntegerVectorConverter``
            ``int2vector`` or ``oidvector`` <-> ``array<int|string>``. These types are used only in system catalogs
            and are not documented.

        ``geometric\LineConverter``
            ``line`` <-> instance of ``Line``. This converter does not extend ``geometric\BaseGeometricConverter``
            as the ``line`` type is represented by three ``float`` coefficients of linear equation and does not
            depend on ``point`` type.

        ``containers\MultiRangeConverter``
            Multirange type (Postgres 14+) <-> instance of ``MultiRange``. The converter instance is configured
            by an instance of range subtype converter and possibly a classname of custom ``MultiRange`` subclass:

            .. code-block:: php

                $intMultiRangeConverter    = new MultiRangeConverter(new IntegerConverter());
                $customMultiRangeConverter = new MultiRangeConverter(new CustomConverter(), CustomMultiRange::class);

        ``geometric\PointConverter``
            Postgres ``point`` type <-> instance of ``Point``.

        ``containers\RangeConverter``
            Range type <-> instance of ``Range``. The converter instance is configured
            by an instance of range subtype converter and possibly a classname of custom ``Range`` subclass:

            .. code-block:: php

                $dateRangeConverter   = new RangeConverter(new DateConverter());
                $customRangeConverter = new RangeConverter(new CustomConverter(), CustomRange::class);

        ``TidConverter``
            Postgres ``tid`` type (represents physical location of a row within a table) <-> instance of ``Tid``.

        ``geometric\BaseGeometricConverter``
            Abstract base class for converters of geometric types. All the types converted by subclasses of this
            are based on the ``point`` type and use ``PointConverter`` to process their own string representations.

            ``geometric\BoxConverter``
                Postgres ``box`` type <-> instance of ``Box``

            ``geometric\CircleConverter``
                Postgres ``circle`` type <-> instance of ``Circle``

            ``geometric\LSegConverter``
                Postgres ``lseg`` type <-> instance of ``LineSegment``

            ``geometric\PathConverter``
                Postgres ``path`` type <-> instance of ``Path``

            ``geometric\PolygonConverter``
                Postgres ``polygon`` type <-> instance of ``Polygon``
