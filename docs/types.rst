.. _complex-types:

==================================
Classes for complex Postgres types
==================================

Generally pg_wrapper tries to use native PHP constructs and built-in classes to represent Postgres types:
associative arrays for composite types and ``hstore``, ``\DateTimeImmutable`` for date and time types, etc.

However, some of the more complex types supported by Postgres do not have suitable PHP equivalents. For these
the package returns instances of the classes described below. Those are immutable and expose the relevant parts
as ``public readonly`` properties or read-only array offsets.

All of the below classes and interfaces, except builtin ones prefixed by a backslash,
belong to ``\sad_spirit\pg_wrapper\types`` namespace, which is omitted for brevity.

``ArrayRepresentable`` interface
================================

The below classes usually implement both ``\JsonSerializable`` interface and this one,
behaving as a pair for ``\JsonSerializable``.

The interface defines a single "named constructor" method:

.. code-block:: php

    interface ArrayRepresentable
    {
        public static function createFromArray(array $input): static;
    }

The method will always accept an array that was created by ``\json_decode(\json_encode($object), true)``
and the result will be an object equal to the original one. It may also accept additional array formats.

If a class representing Postgres type implements this interface, then :ref:`type converter <converters-implementations>`
for that type will accept an array as argument for its ``output()`` method passing it to ``createFromArray()``
constructor of a relevant class.

Range types
===========

Range types represent a range of values of some element type (called the range's *subtype*). Postgres provides several
built-in range types and allows creating custom ones using
`CREATE TYPE <https://www.postgresql.org/docs/current/sql-createtype.html>`__ command.

A non-empty range has two bounds, the lower and the upper. All points between these values are included
in the range. An inclusive bound means that the boundary point itself is included in the range,
while an exclusive bound means that it is not included. Using ``null`` for a bound means that
the range is unbounded on that side.

An empty range is the one that does not contain points.

Considering the above, the base ``Range`` class has the following properties

.. code-block:: php

    /**
     * @template Bound
     */
    readonly class Range implements ArrayRepresentable, RangeConstructor, \JsonSerializable
    {
        /** @var Bound|null */
        public mixed $lower;
        /** @var Bound|null */
        public mixed $upper;
        public bool $lowerInclusive;
        public bool $upperInclusive;
        public bool $empty;
    }

``RangeConstructor`` interface fixes the signature of ``__construct()`` method so that ``new static()`` calls
work as expected in subclasses:

.. code-block:: php

    interface RangeConstructor
    {
        public function __construct(
            mixed $lower = null,
            mixed $upper = null,
            bool $lowerInclusive = true,
            bool $upperInclusive = false,
            bool $empty = false
        );
    }

If ``$empty`` constructor argument is ``true`` then an empty range is created, all other values are essentially ignored.
Another way to create an empty range is the static ``createEmpty()`` method.

``jsonSerialize()`` method returns ``['empty' => true]`` array for empty ranges and an array with 'lower', 'upper',
'lowerInclusive', and 'upperInclusive' keys for non-empty ones. ``createFromArray()`` can process both of these
formats, additionally it will use first two elements of any other array for ``$lower`` and ``$upper`` bounds:

.. code-block:: php

    // Both will create an instance of NumericRange with $lower = 1 and $upper = 10
    $rangeOne = NumericRange::createFromArray(['upper' => 10, 'lower' => 1]);
    $rangeTwo = NumericRange::createFromArray([1, 10, 'this will be ignored']);

``Range`` has two subclasses that represent the built-in range types of Postgres:

``DateTimeRange``
    Values of ``tsrange``, ``tstzrange``, ``daterange`` types are converted to this. Non-null ``$lower`` and ``$upper``
    bounds are instances of ``\DateTimeImmutable``.

``NumericRange``
    Values of ``int4range``, ``int8range``, ``numrange`` types are converted to this. Non-null ``$lower`` and ``$upper``
    bounds can be ``int``, ``float`` or ``numeric-string``.

Constructors of these subclasses enforce the types of ``$lower`` and ``$upper`` bound values, check that the ``$lower``
bound is less than or equal to the ``$upper`` (otherwise the range is invalid in Postgres) and create an empty range
if ``$lower == $upper`` and at least one of the bounds is exclusive.

.. note::
    By default, values of *custom* range types will be converted to instances of base ``Range``, which means that there
    will be no checks of ``$lower`` and ``$upper`` values. It may make sense to create a custom subclass of ``Range``
    for such a custom type and configure  ``RangeConverter`` / ``DefaultTypeConverterFactory`` to return instances
    of that subclass.

Multirange types
================

Multirange types, available since Postgres 14, represent lists of ranges. Each range type has a corresponding
multirange one.

Classes representing multirange types on PHP side behave like read-only lists of ``Range`` instances, they
extend the base ``MultiRange`` class:

.. code-block:: php

    /**
     * @template T of Range
     */
    readonly class MultiRange
    implements ArrayRepresentable, \ArrayAccess, \Countable, \IteratorAggregate, \JsonSerializable
    {
        /**
         * @return class-string<T>
         */
        public static function getItemClass(): string;

        /**
         * @param T ...$items
         */
        final public function __construct(Range ...$items);
    }

Here ``ArrayAccess`` is implemented read-only, with ``offsetSet()`` and ``offsetUnset()`` throwing exceptions.
Child classes should override ``getItemClass()`` to return the class name of ``Range`` subclass they accept.

As with the ``Range`` class above, ``MultiRange`` has subclasses representing the built-in multirange types:

``DateTimeMultiRange``
    Contains only instances of ``DateTimeRange``, values of ``tsmultirange``, ``tstzmultirange``, ``datemultirange``
    types are converted to this.

``NumericMultiRange``
    Contains only instances of ``NumericRange``, values of ``int4multirange``, ``int8multirange``, ``nummultirange``
    types are converted to this.

.. note::
    By default, values of *custom* multirange types will be converted to instances of base ``MultiRange``,
    which essentially accepts any ``Range`` instance as its element. It may make sense to create a custom subclass
    of ``MultiRange`` that restricts the accepted objects and configure ``MultiRangeConverter`` /
    ``DefaultTypeConverterFactory`` to return instances of that subclass.

Geometric types
===============

Postgres geometric types except ``line`` are backed by ``point`` type: it represents a point
in two-dimensional space with its ``x`` and ``y`` coordinates specified as floating-point numbers.

``point`` values are converted to instances of the ``Point`` class:

.. code-block:: php

    final readonly class Point implements ArrayRepresentable, \JsonSerializable
    {
        public function __construct(
            public float $x,
            public float $y
        ) {
        }
    }

Its ``jsonSerialize()`` method returns an array with 'x' and 'y' keys. Its ``createFromArray()`` accepts any array
with exactly two elements and either

- uses the values with 'x' and 'y' keys for coordinates or
- uses the first array element for ``$x`` and the second one for ``$y`` if there are no such keys.

.. code-block:: php

    // Both will create Point with $x = 1.2 and $y = 3.4
    $pointOne = Point::createFromArray(['y' => 3.4, 'x' => 1.2]);
    $pointTwo = Point::createFromArray([1.2, 3.4]);

``Box`` and ``LineSegment``
---------------------------

``box`` type in Postgres is used for representing a rectangular box and ``lseg`` is for a finite line segment.
Both of these are specified by two ``point`` values: start and end for ``lseg`` and opposite corners for ``box``.

Those types are converted to ``Box`` and ``LineSegment`` instances

.. code-block:: php

    abstract readonly class PointPair implements ArrayRepresentable, \JsonSerializable
    {
        final public function __construct(
            public Point $start,
            public Point $end
        ) {
        }
    }

    final readonly class Box extends PointPair
    {
    }

    final readonly class LineSegment extends PointPair
    {
    }

``jsonSerialize()`` method returns an array with 'start' and 'end' keys. ``createFromArray()`` accepts any array
with exactly two elements and either

- uses the values with 'start' and 'end' keys for relevant points or
- uses the first element for ``$start`` and the second one for ``$end`` if there are no such keys.

Values in the array may be either instances of ``Point`` or arrays suitable for ``Point::createFromArray()``

``Path`` and ``Polygon``
------------------------

``path`` type is a list of connected points. ``path`` can be open, when the first and the last points
are considered not connected and closed, when they are connected. ``polygon`` is represented by a list of points
that are vertices of a polygon, it is quite similar to a closed ``path``.

Those types are converted to ``Path`` and ``Polygon`` instances which behave like read-only lists of ``Point``:

.. code-block:: php

    abstract readonly class PointList implements \ArrayAccess, \Countable, \IteratorAggregate
    {
        public function __construct(Point ...$points);
    }

    final readonly class Path extends PointList implements ArrayRepresentable, \JsonSerializable
    {
        public function __construct(
            public bool $open,
            Point ...$points
        );
    }

    final readonly class Polygon extends PointList implements ArrayRepresentable, \JsonSerializable
    {
    }

``ArrayAccess`` is implemented read-only, with ``offsetSet()`` and ``offsetUnset()`` throwing exceptions.

``Polygon::jsonSerialize()`` returns a list of points, its ``createFromArray()`` accepts an array with its elements
being either instances of ``Point`` or arrays suitable for ``Point::createFromArray()``.

``Path::jsonSerialize()`` returns a list with the first element being a ``bool`` value representing
the ``$open`` property and all other elements being points. Its ``createFromArray()`` can accept an array
of the same structure, or just an array of points, with ``$open`` defaulting to ``false``.

``Circle``
----------

``circle`` type is represented by a center ``point`` and floating-point radius.
Values of this type are converted to instances of ``Circle``:

.. code-block:: php

    final readonly class Circle implements ArrayRepresentable, \JsonSerializable
    {
        public function __construct(
            public Point $center,
            public float $radius
        ) {
        }
    }

Its ``jsonSerialize()`` method returns an array with 'center' and 'radius' keys. Its ``createFromArray()`` accepts
any array with exactly two elements and either

- uses the values with 'center' and 'radius' keys for ``$center`` and ``$radius`` or
- uses the first element for ``$center`` and the second one for ``$radius`` if there are no such keys.

``Line``
--------

Lines are represented by the linear equation ``Ax + By + C = 0``, where ``A`` and ``B`` are not both zero. Values
of this type are converted to instances of ``Line``:

.. code-block:: php

    final readonly class Line implements ArrayRepresentable, \JsonSerializable
    {
        public function __construct(
            public float $A,
            public float $B,
            public float $C
        ) {
        }
    }

As usual, its ``jsonSerialize()`` method returns an array with 'A', 'B', and 'C' keys. Its ``createFromArray()``
accepts any array with exactly three elements and either

- uses the values with 'A', 'B', and 'C' keys for ``$A``, ``$B``, and ``$C`` or
- uses the first element for ``$A``, the second for ``$B``, and the third for ``$C`` if there are no such keys.

.. note::
    Postgres also accepts the literal similar to ``lseg`` (two different points on the line) as input
    for ``line`` type. You can simply use the ``LineSegment`` class described above and / or its converter to create
    such a literal.

``Tid``
=======

Instances of this are returned for values of Postgres ``tid`` type that represents the physical location of
a tuple (row) within a table.

.. code-block:: php

    final readonly class Tid implements ArrayRepresentable, \JsonSerializable
    {
        public int|string $block;
        public int $tuple;

        public function __construct(int|string $block, int $tuple);
    }

Both ``$block`` (block number) and ``$tuple`` (index of tuple within block) properties are non-negative integers,
``$block`` may be a string on 32-bit builds of PHP as it is an *unsigned* 32-bit integer like ``oid``.

Its ``jsonSerialize()`` method returns an array with 'block' and 'tuple' keys. Its ``createFromArray()`` accepts
any array with exactly two elements and uses either

- the values with 'block' and 'tuple' keys for corresponding properties,
- or the first array element for ``$block`` and the second one for ``$tuple`` if there are no such keys.
