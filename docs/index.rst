=====================
sad_spirit/pg_wrapper
=====================

This package has two parts and purposes

- Converter of `PostgreSQL data types <https://www.postgresql.org/docs/current/datatype.html>`__ to their PHP
  equivalents and back and
- An object oriented wrapper around PHP's native `pgsql extension <https://php.net/manual/en/book.pgsql.php>`__.

While the converter part can be used separately e.g. with `PDO <https://www.php.net/manual/en/book.pdo.php>`__,
features like transparent conversion of query results work only with the wrapper.

A similar wrapper for PDO is possible, but is neither implemented nor planned as it will have to depend
on highly inefficient :ref:`handling of column metadata <converter-factories-oids>` in PDO_pgsql driver.

.. toctree::
   :maxdepth: 3
   :caption: Contents:

   overview
   types
   converters
   converter-factories
   connecting
   queries
   result
   transactions
   exceptions
   decorators
