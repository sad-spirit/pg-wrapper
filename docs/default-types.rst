
.. _converter-factories-names:

==============================
Types supported out of the box
==============================

This section contains a list of base type names and names of built-in range types understood by
``DefaultTypeConverterFactory``, those can be converted without setting up the ``Connection``. This allows
using the factory separately e.g. with PDO.

The preconfigured mappings from PHP class names to database types are also given.

Type names known to ``DefaultTypeConverterFactory``
===================================================

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
================================

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
