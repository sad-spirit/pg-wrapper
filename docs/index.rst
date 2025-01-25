=====================
sad_spirit/pg_wrapper
=====================

This package has two parts and purposes

- Converter of `PostgreSQL data types <https://www.postgresql.org/docs/current/datatype.html>`__ to their PHP
  equivalents and back and
- An OO wrapper around PHP's native `pgsql extension <https://php.net/manual/en/book.pgsql.php>`__.

While the converter part can be used separately e.g. with `PDO <https://www.php.net/manual/en/book.pdo.php>`__,
features like transparent conversion of query results work only with the wrapper.

.. toctree::
   :maxdepth: 3
   :caption: Contents:

   overview
   types
   converters
   converter-factories
