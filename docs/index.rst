=====================
sad_spirit/pg_wrapper
=====================

This package has two parts and purposes

- Converter of `PostgreSQL data types <https://www.postgresql.org/docs/current/datatype.html>`__ to their PHP
  equivalents and back and
- An object oriented wrapper around PHP's native `pgsql extension <https://php.net/manual/en/book.pgsql.php>`__.

The converter part can be used separately, this will require :ref:`manually specifying types and calling the
type conversion methods <tutorial-types>`.
:ref:`Using the wrapper part <tutorial-wrapper>` provides transparent conversion of query results
and easier conversion of parameter values for parametrized queries.

.. toctree::
   :maxdepth: 3
   :caption: Contents:

   overview
   tutorial-types
   types
   converters
   converter-factories
   default-types
   internals-oids
   howto-enums
   howto-base
   tutorial-wrapper
   connecting
   queries
   result
   transactions
   exceptions
   decorators
