.. _converter-factories-enum:

===========================================
How to map Postgres ENUM types to PHP enums
===========================================

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

then ``DefaultTypeConverterFactory`` should be configured in the following way

.. code-block:: php

    use sad_spirit\pg_wrapper\converters\EnumConverter;

    $factory->registerConverter(static function () {
        return new EnumConverter(MetaSyntactic::class);
    }, 'syntactic', 'meta');
    $factory->registerClassMapping(MetaSyntactic::class, 'syntactic', 'meta');

The ``registerConverter()`` call makes ``$factory`` return a configured instance of ``EnumConverter`` when asked
for a converter for ``meta.syntactic`` type, so you'll get cases of ``MetaSyntactic`` instead of strings in
a query result.

The ``registerClassMapping()`` call makes ``$factory`` request a converter for ``meta.syntactic`` type when
a case of ``MetaSyntactic`` is given as a parameter value for a parametrized query, without the need to specify type.


