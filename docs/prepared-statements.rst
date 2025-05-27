
.. _queries-prepared:

===================
Prepared statements
===================

The two main benefits of using prepared statements are

 - Query parameters are passed separately from query text;
 - Query execution plan can be cached, this saves time for parsing / planning on subsequent executions.

Since Postgres offers a method to pass query text separately from parameters without the need to prepare,
see ``executeParams()`` method described in :ref:`the previous chapter <queries>`, it mostly makes
sense to use the below API for queries that will be executed multiple times.


``PreparedStatement`` class
===========================

.. note::
    Instances of this class are created by ``Connection::prepare()`` method, ``PreparedStatement::__construct()``
    is marked internal and should not be used outside of ``Connection`` methods.

The statement is automatically prepared when an instance of ``PreparedStatement`` is created and automatically
deallocated when the object is destroyed. Manual methods are also available just in case:

``public function prepare(): $this``
    Actually prepares the statement with `pg_prepare() <https://www.php.net/manual/en/function.pg-prepare.php>`__.

``public function deallocate(): $this``
    Manually deallocates the prepared statement using ``DEALLOCATE ...`` SQL statement.

    Trying to call ``execute()`` / ``executeParams()`` after ``deallocate()`` will result in an ``Exception``.

A very useful method allows specifying the number of parameters in the query:

``public function setNumberOfParameters(int $numberOfParameters): $this``
    Sets number of parameters used in the query.

    Parameter symbols should start with ``$1`` and have no gaps in numbers, otherwise Postgres will throw an error,
    so setting their number is sufficient.

.. code-block:: php

    // If we know the number of parameters...
    $prepared->setNumberOfParameters(2);
    // ...then all the below methods will throw exceptions
    $prepared->executeParams([1, 2, 3]);
    $prepared->bindValue(4, 'foo');
    $prepared->setParameterType(5, 'integer');

.. tip::
    Number of parameters will always be set to a correct value by ``fetchParameterTypes()``, so
    there is no need to call ``setNumberOfParameters()`` unless automatic fetching of parameter types is disabled.

Supplying parameter values
==========================

There are two ways to supply parameters for a prepared statement, the first one is binding the parameters and
calling ``execute()``

``public function bindValue(int $parameterNumber, mixed $value, mixed $type = null): $this``
    Sets the value for a parameter of a prepared query.

    ``$parameterNumber`` is 1-based, ``$type`` contains specification of parameter type. An exception will be raised
    if ``$type`` is omitted / ``null`` and the parameter type is not already known.

``public function bindParam(int $parameterNumber, mixed &$param, mixed $type = null): $this``
    Binds a variable to a parameter of a prepared query.

``public function execute(): Result``
    Executes a prepared query using previously bound values. Note that the method does not accept arguments, all
    values should be bound.

.. code-block:: php

    $prepared = $connection->prepare('select * from foo where bar_id = $1 and foo_deleted = $2');
    $result   = $prepared
        ->bindValue(1, 10)
        ->bindValue(2, false)
        ->execute();

The second way is just

``public function executeParams(array $params): Result``
    Executes the prepared query using (only) the given parameters.

    ``$params`` should have integer keys with (0-based) key ``N`` corresponding to (1-based) statement placeholder
    ``$(N + 1)``. Unlike native `pg_execute() <https://www.php.net/manual/en/function.pg-execute.php>`__, array keys
    will be respected and values mapped by keys rather than in "array order": passing ``['foo', 'bar']`` will use
    'foo' for ``$1`` and 'bar' for ``$2``, while ``[1 => 'foo', 0 => 'bar']`` will use
    'bar' for ``$1`` and 'foo' for ``$2``.

.. code-block:: php

    $prepared = $connection->prepare('select * from foo where bar_id = $1 and foo_deleted = $2');
    $result   = $prepared->executeParams([10, false]);


.. note::
    These approaches are mutually exclusive, ``executeParams()`` will throw an exception if any parameter
    has a bound value.

Fetching parameter types automatically
======================================

By default, ``PreparedStatement`` gets the types of the query parameters from Postgres (specifically, from
``pg_prepared_statements`` system view), so there is no need to pass type specifications at all:

.. code-block:: php

    $prepared = $connection->prepare(
        'select * from pg_catalog.pg_type where oid = any($1) order by typname'
    );
    $result   = $prepared->executeParams([[16, 20, 603]]);

This behaviour is controlled by static methods

``public static function setAutoFetchParameterTypes(bool $autoFetch): void``
    Sets whether parameter types should be automatically fetched after first preparing a statement.

``public static function getAutoFetchParameterTypes(): bool``
    Returns whether parameter types will be automatically fetched after first preparing a statement.
    This defaults to ``true`` since version 3.0

Changing that setting will affect all ``PreparedStatement`` objects created afterwards.

The method that fetches types can also be called manually

``public function fetchParameterTypes(bool $overrideExistingTypes = false): $this``
    Fetches info about the types assigned to query parameters from the database.

    This method will always set parameter count to a correct value, but will not change existing type converters
    for parameters unless ``$overrideExistingTypes`` is ``true``.

Specifying types manually
=========================

It is assumed that the statement will be executed multiple times and that types of parameters and result columns
are quite unlikely to change between executions. Therefore, both query execution methods do not accept
type specifications and ``executeParams()`` will throw an exception if a type for a parameter is not known.

Both parameter types and result types can be specified either when preparing a statement

.. code-block:: php

    $prepared = $connection->prepare(
        'select row(foo_id, foo_added) from foo where bar = any($1::integer[])',
        ['integer[]'],
        [['id' => 'integer', 'added' => 'timestamptz']]
    );

or using the methods of ``PreparedStatement`` instance

``public function setParameterType(int $parameterNumber, mixed $type): $this``
    Sets the type for a parameter of a prepared query.

``public function setResultTypes(array $resultTypes): $this``
    Sets result types that will be passed to created ``Result`` instances.

Additionally, ``bindValue()`` and ``bindParam()`` accept type specifications as well.