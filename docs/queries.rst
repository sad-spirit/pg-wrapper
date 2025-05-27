.. _queries:

=================
Executing queries
=================

There are three ways to execute a query:

- ``Connection::execute()`` passing SQL string (this uses
  `pg_query() <https://php.net/manual/en/function.pg-query.php>`__ internally);
- ``Connection::executeParams()`` passing SQL string containing placeholders (``$1``, ``$2``, ...) and separate
  parameter values (this uses
  `pg_query_params() <https://php.net/manual/en/function.pg-query-params.php>`__ internally);
- ``Connection::prepare()`` passing SQL string (usually) containing placeholders to create an instance of
  ``PreparedStatement``, then call ``PreparedStatement::execute()`` / ``PreparedStatement::executeParams()``
  providing separate parameter values (this uses `pg_prepare() <http://php.net/manual/en/function.pg-prepare.php>`__
  and `pg_execute() <http://php.net/manual/en/function.pg-execute.php>`__ internally). This way is described
  in :ref:`the next chapter <queries-prepared>`.

All of the above methods return an instance of ``Result``.

Passing query parameters
========================

While it is possible to embed query parameters directly into SQL string

.. code-block:: php

   $sql = 'select * from articles where article_title ~ ' . $connection->quote($titleFilter)
          . ' or article_author = ' . $connection->quote($authorId);
   $res = $connection->execute($sql);

this approach may lead to security issues if ``quote()`` is not applied thoroughly.
It is only recommended to use ``Connection::execute()`` for queries that do not have parameters.

On the other hand using ``prepare()`` / ``execute()`` workflow has an obvious
performance issue with queries that are executed once: it requires two round-trips to the database instead of one.

So ``prepare()`` / ``execute()`` are best used for queries that are executed multiple times with different parameters,
especially for complex ones where time spent on parsing / planning stage is substantial.

If a query contains parameters *and* will be executed only once the best approach is ``executeParams()``:

.. code-block:: php

   $res = $connection->executeParams(
       'select * from articles where article_title ~ $1 or article_author = $2',
       [$titleFilter, $authorId]
   );

This gives the benefits of previous approaches without their shortcomings:

- Parameters are passed separately from query, this makes SQL injection far less likely;
- Only one database round-trip is required.


Specifying parameter types
==========================

``Connection::executeParams()`` also accepts type specifications for parameters:

.. code-block:: php

   $result = $connection->executeParams(
       'select * from articles where article_id = any($1::integer[])',
       [[1, 2, 3]],
       ['integer[]']
   );

``PreparedStatement`` will try to get proper parameter types from Postgres by default, but
these can be overridden / specified manually via ``Connection::prepare()``, ``PreparedStatement::setParameterType()``,
``PreparedStatement::bindValue()``, and ``PreparedStatement::bindParam()``.

.. code-block:: php

   $prepared = $connection->prepare(
       'select * from articles where article_id = any($1::integer[])'
   );
   $prepared->bindValue(1, [4, 5, 6], new ArrayConverter(new IntegerConverter()));

These type specifications are processed by an implementation of ``TypeConverterFactory`` set for ``Connection`` via
``setTypeConverterFactory()`` method. The :ref:`default implementation <converter-factories-default>`
will accept either of the following:

- :ref:`Type name as string <converter-factories-names>`. As shown above, array types can be specified using
  square brackets: ``typename[]``.
- Composite type specification as an array ``'column' => 'column type specification'``.
- ``TypeConverter`` instance, it will receive current ``Connection`` to update its configuration, if needed.

It is not necessary to provide type information for *every* parameter: some may be skipped or type info omitted
altogether. In this case an attempt will be made to guess which converter to use based on PHP
variable type.

.. tip::
    You *must* specify the type if the parameter is a PHP array as in above examples, guessing will definitely fail.
    If the parameter is a scalar or :ref:`an instance of a known class <converter-factories-classes>`
    then guessing will probably work.

.. _queries-result:

Specifying result column types
==============================

Generally you don't need to specify types for columns in query result: these are deduced from result metadata.

One notable exception is a column defined by a row type constructor:

.. code-block:: php

   $composite = $conn->execute("select ROW('fuzzy dice', 42, 1.99) as needstype");
   var_dump($composite[0]['needstype']);

the above will output

.. code-block:: output

   string(22) "("fuzzy dice",42,1.99)"

as Postgres specifies its type as a generic ``record`` pseudo-type. To provide necessary type information
for a ``Result`` you can either pass it to ``execute()`` / ``executeParams()``:

.. code-block:: php

   $composite = $conn->execute(
       "select ROW('fuzzy dice', 42, 1.99) as needstype",
       [['text', 'int4', 'float8']]
   );
   var_dump($composite[0]['needstype']);

or call ``setType()`` on the ``Result`` instance:

.. code-block:: php

   $composite = $conn->execute("select ROW('fuzzy dice', 42, 1.99) as needstype");
   $composite->setType('needstype', ['text', 'int4', 'float8']);
   var_dump($composite[0]['needstype']);

both of the above will output

.. code-block:: output

   array(3) {
     [0] =>
     string(10) "fuzzy dice" 
     [1] =>
     int(42)
     [2] =>
     double(1.99)
   }

Query-related methods of ``Connection``
=======================================

The methods for query execution were mostly covered above

``public function execute(string $sql, array $resultTypes = []): Result``
    Executes a given query. ``$resultTypes`` information is passed to ``Result`` and overrides automatically
    determined types.

``public function executeParams(string $sql, array $params, array $paramTypes = [], array $resultTypes = []): Result``
    Executes a given query with the ability to pass parameters separately. The query should contain positional
    placeholders ``$1``, ``$2``, … that will be replaced by ``$params`` on execution.

    ``$paramTypes`` specify the types for query parameters, ``$resultTypes`` information will be passed to ``Result``.

``public function prepare(string $query, array $paramTypes = [], array $resultTypes = []): PreparedStatement``
    Prepares a given query for execution, returning a ``PreparedStatement`` object. As with ``executeParams()``,
    the query will usually contain positional placeholders.

    ``$paramTypes`` specify types for parameters, ``$resultTypes`` will (eventually) be passed to ``Result``.

Methods that help with embedding stuff directly in SQL are also available, but their use is discouraged:

``public function quote(mixed $value, mixed $type = null): string``
    Quotes a value for inclusion in a query, taking connection encoding into account.
    This is only needed when building a query by hand:

    .. code-block:: php

        $sql .= 'WHERE foo = ' . $connection->quote($foo);

    It is recommended to pass parameters separately from query instead.

``public function quoteIdentifier(string $identifier): string``
    Quotes an identifier (e.g. table or column name) for inclusion in a query.
    It is a bad idea to take ``$identifier`` from user input even if using this method.
