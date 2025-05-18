========================================
How to add converters for new base types
========================================

A custom converter is only needed for a new base type, as values of the derived types can be converted by configuring
the existing ones.

Notably, pg_wrapper lacks converters for types like ``uuid`` and ``inet``. The main reason is that properly
implementing PHP objects to support these types is way out of scope for the package, while using some external
implementation will introduce unnecessary dependencies.

The package contains abstract ``BaseConverter`` and ``ContainerConverter`` classes, the new converter implementation
should probably extend one of these based on the properties of the type.

``BaseConverter``
=================

This base class implements ``input()`` and ``output()`` methods that handle null values as ``pgsql`` extension itself
converts ``NULL`` fields of any type to PHP ``null`` values.

It delegates handling of non-null values to the two new abstract methods

``inputNotNull(string $native): mixed``
    Converts a string received from PostgreSQL to PHP variable of the proper type.

``outputNotNull(mixed $value): string``
    Returns a string representation of PHP variable not identical to null.

``ContainerConverter``
======================

This class defines helper methods for parsing complex string representations. Those accept the string
received from the database and position of the current symbol that is updated once parts of the string is processed.

``nextChar(string $str, int &$p): ?string``
    Gets next non-whitespace character from input, position is updated. Returns ``null`` if input ended.

``expectChar(string $string, int &$pos, string $char): void``
    Throws a ``TypeConversionException`` if next non-whitespace character in input is not the given char, moves
    to the next symbol otherwise.

``inputNotNull()`` is implemented in ``ContainerConverter``, a new abstract method is defined instead

``parseInput(string $native, int &$pos): mixed``
    Parses a string representation into PHP variable from given position. This may be called from any position in
    the string and should return once it finishes parsing the value.

Adding converter for ``uuid`` type
==================================

We will use the obvious choice for representing UUIDs on PHP side: `ramsey/uuid package <https://github.com/ramsey/uuid>`__.

The converter class will extend ``BaseConverter`` as it will not actually parse UUIDs itself.

.. code-block:: php

    namespace sad_spirit\pg_wrapper\converters;

    use Ramsey\Uuid\Uuid;
    use Ramsey\Uuid\UuidInterface;
    use sad_spirit\pg_wrapper\exceptions\TypeConversionException;

    class UuidConverter extends BaseConverter
    {
        protected function inputNotNull(string $native): UuidInterface
        {
            return Uuid::fromString($native);
        }

        protected function outputNotNull($value): string
        {
            if ($value instanceof UuidInterface) {
                return $value->toString();
            } elseif (\is_string($value)) {
                // We can validate a string here, but Postgres is a bit more lax with formats than ramsey/uuid
                return $value;
            }
            throw TypeConversionException::unexpectedValue($this, 'output', 'a string or an implementation of UuidInterface', $value);
        }
    }

The converter should be registered with ``DefaultTypeConverterFactory`` in the following way

.. code-block:: php

    use Ramsey\Uuid\UuidInterface;
    use sad_spirit\pg_wrapper\converters\UuidConverter;

    $factory->registerConverter(UuidConverter::class, 'uuid');
    $factory->registerClassMapping(UuidInterface::class, 'uuid');

Adding a mapping for ``UuidInterface`` will allow using implementations of that as parameter values without the
need to specify types.
