.. _exceptions:

=================
Exception classes
=================

pg_wrapper contains a base exception interface and several specialized exception classes that implement it.

The exceptions that represent database errors are created based on ``SQLSTATE`` error codes and contain those codes,
this allows handling errors not relying on (often localized) error messages.

Base exception interface
========================

The package contains base exception interface ``\sad_spirit\pg_wrapper\Exception`` and several specialized exception
classes that extend `SPL Exception classes <https://www.php.net/manual/en/spl.exceptions.php>`__ and implement
this interface. Therefore all exceptions thrown in ``pg_wrapper`` can be caught like this

.. code-block:: php

    use sad_spirit\pg_wrapper\Exception as PackageException;

    try {
        // Do some database-related stuff
    } catch (PackageException $e) {
        // Database-related exception, it is usually a bad idea to show its message to the user
    }

It is also possible to catch specific SPL exceptions:

.. code-block:: php

    try {
        // Do some database-related stuff
    } catch (\LogicException $e) {
        // Probably a bug in the code
    }

Exceptions hierarchy
====================

All the exception classes below belong to ``sad_spirit\pg_wrapper\exceptions`` namespace:

``BadMethodCallException extends \BadMethodCallException``
    Namespaced version of
    `SPL's BadMethodCallException <https://www.php.net/manual/en/class.badmethodcallexception.php>`__

    Thrown if method call is either disallowed altogether, e.g. ``Result::offsetSet()``,
    or disallowed in current context, e.g. ``Connection::onCommit()`` outside of ``atomic()`` closure.

``InvalidArgumentException extends \InvalidArgumentException``
    Namespaced version of
    `SPL's InvalidArgumentException <https://www.php.net/manual/en/class.invalidargumentexception.php>`__

    Thrown e.g. by ``DefaultTypeConverterFactory::getConverterForTypeSpecification()`` if an invalid type name
    was provided.

``OutOfBoundsException extends \OutOfBoundsException``
    Namespaced version of
    `SPL's OutOfBoundsException <https://www.php.net/manual/en/class.outofboundsexception.php>`__

    Thrown e.g. by ``Result`` methods if a non-existent column name / index was given.

``TypeConversionException extends \DomainException``
    Thrown when conversion of value from/to database representation fails. This is thrown almost exclusively in
    :ref:`type converters <converters>`.

``RuntimeException extends \RuntimeException``
    Namespaced version of
    `SPL's RuntimeException <https://www.php.net/manual/en/class.runtimeexception.php>`__

    Thrown e.g. by ``Connection::createSavepoint()`` if called outside of transaction block.

    ``ServerException``
        Base class for exceptions coming from Postgres. Defines ``getSqlState(): ?SqlState`` method returning a case
        of :ref:`SqlState enum <exceptions-enum>` for an error code, if one was available.

        ``ConnectionException``
            Thrown when database connection fails / is broken.

        ``server\ConstraintViolationException``
            Thrown when database integrity constraint is violated. It defines an additional
            ``getConstraintName(): ?string`` method returning name of that constraint
            if one is available.

        ``server\DataException``
            Thrown when there are problems with processed data, like division by zero or numeric value out of range.

        ``server\FeatureNotSupportedException``
            Thrown when an attempt to use functionality not supported by Postgres is made.

        ``server\InsufficientPrivilegeException``
            Thrown when an action fails due to insufficient permissions.

        ``server\InternalErrorException``
            Thrown when a database encounters some internal error: e.g. transaction state is invalid.

            Most commonly this happens when trying to execute anything except ``ROLLBACK`` after previous error
            in transaction block.

        ``server\OperationalException``
            Thrown for errors related to database's operation that are not necessarily under the control of
            programmer.

            ``server\QueryCanceledException``
                Thrown when query is canceled, either due to ``statement_timeout`` setting or user request.

            ``server\TransactionRollbackException``
                Thrown when transaction is rolled back due to deadlock or serialization failure.

                Unlike other exceptions, queries that caused this one often *need* to be repeated.

        ``server\ProgrammingException``
            Thrown for programming errors: invalid SQL syntax, undefined or ambiguous objects, etc.


Generally, if a query fails:

- ``ConnectionException`` is thrown when connection to server failed;
- Appropriate subclass of ``ServerException`` is thrown based on ``SQLSTATE`` error
  code when said code is available and accepted by ``SqlState`` enum;
- Generic ``ServerException`` otherwise.

.. _exceptions-enum:

``SqlState`` enum
=================

``\sad_spirit\pg_wrapper\exceptions\SqlState`` is a string-backed ``enum`` representing
`PostgreSQL error codes <https://www.postgresql.org/docs/current/static/errcodes-appendix.html>`__.

Its case names are "condition names" from the table in the above link, these are used in PL/PgSQL
for exception handling

.. code-block:: postgres

    do $$
        declare x numeric;
        begin
            x := 1 / 0;
        exception
            when division_by_zero then
                raise notice 'caught division_by_zero';
        end;
    $$;

Catching the same error in PHP:

.. code-block:: php

    use sad_spirit\pg_wrapper\exceptions\ServerException;
    use sad_spirit\pg_wrapper\exceptions\SqlState;

    try {
        $connection->execute('select 1 / 0');
    } catch (ServerException $e) {
        if (SqlState::DIVISION_BY_ZERO === $e->getSqlState()) {
            echo 'caught division_by_zero';
        }
    }

The backing strings for ``SqlState`` cases are five-character error codes that follow the SQL standard's conventions.
First two characters of an error code denote a class of errors, while the last three characters indicate
a specific condition within that class. Each class contains a “standard” error code having ``000``
for the last three characters.

The enum has a ``genericSubclass()`` method returning a case with such a standard error code for the given case,
this is used by ``ServerException::fromConnection()`` for creating a proper subclass of itself.