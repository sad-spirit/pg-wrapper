.. _result:

===========================
Working with a query result
===========================

All the :ref:`query execution methods <queries>` return an instance of ``\sad_spirit\pg_wrapper\Result``,
which encapsulates the `native \\PgSql\\Result <https://www.php.net/manual/en/class.pgsql-result.php>`__.
It uses the the type converter factory (the same one ``Connection``
:ref:`was configured with <connecting-configuration>`) to convert strings in query result to PHP values.

If the query did not return any rows, the object's only useful method will be ``getAffectedRows()``,
otherwise it provides several ways to iterate over the result.

``Result`` public API
=====================

.. note::
    Instances of this class are created in ``Connection`` and ``PreparedStatement`` methods.
    Its ``__construct()`` method is protected, and its "named constructor" method
    ``createFromReturnValue()`` is marked internal and shouldn't be used outside ``Connection``
    and ``PreparedStatement``.

The public API is as follows

.. code-block:: php

    namespace sad_spirit\pg_wrapper;

    class Result implements \Iterator, \Countable, \ArrayAccess
    {
        // metadata getters
        public function getAffectedRows(): int;
        public function getFieldNames(): string[];
        public function getFieldCount(): int;
        public function getTableOID(int|string $fieldIndex): int|string|null;

        // configuring the returned rows
        public function setType(int|string $fieldIndex, mixed $type): $this;
        public function setMode(int $mode = \PGSQL_ASSOC): $this;

        // fetching all rows as array
        public function fetchColumn(int|string $fieldIndex): array;
        public function fetchAll(
            ?int $mode = null,
            int|string|null $keyColumn = null,
            bool $forceArray = false,
            bool $group = false
        ): array;

        // custom iterators
        public function iterateColumn(int|string $fieldIndex): \Traversable;
        public function iterateNumeric(): \Traversable;
        public function iterateAssociative(): \Traversable;
        public function iterateKeyedAssociative(?string $keyColumn = null, bool $forceArray = false): \Traversable;
        public function iterateKeyedNumeric(int $keyColumn = 0, bool $forceArray = false): \Traversable;

        // Implementations of interface methods omitted
    }


Implementing
`Iterator <https://www.php.net/manual/en/class.iterator.php>`__,
`Countable <https://www.php.net/manual/en/class.countable.php>`__,
and
`ArrayAccess <https://www.php.net/manual/en/class.arrayaccess.php>`__
predefined interfaces allows easy iteration over the query result and access to specific rows:

.. code-block:: php

   echo "The query returned " . count($result) . " rows\r\n";
   if (count($result) > 0) {
       echo "Id of the first row is " . $result[0]['id'] . "\r\n"; 
   }
   foreach ($result as $row) {
       // Do some stuff
   }

Note that ``ArrayAccess`` is implemented read-only for obvious reasons, so trying to do something like

.. code-block:: php

   $result[0] = ['foo', 'bar'];
   unset($result[1]);

will cause a ``BadMethodCallException``.

Getting result metadata
=======================

``public function getAffectedRows(): int``
    Returns number of rows affected by ``INSERT``, ``UPDATE``, and ``DELETE`` queries.

    In case of ``SELECT`` queries this will be equal to what ``count($result)`` returns.

``public function getFieldNames(): string[]``
    Returns the names of fields (columns) in the result.

``public function getFieldCount(): int``
    Returns the number of fields (columns) in the result.

``public function getTableOID(int|string $fieldIndex): int|string|null``
    Returns the ``OID`` for a table that contains the given result field.

    Will return ``null`` if the field is e.g. a literal or a calculated value.

The methods are pretty self-explanatory except ``getTableOID()``. It returns what
`pg_field_table() <https://www.php.net/manual/en/function.pg-field-table.php>`__ with ``$oid_only = true`` would
return for that field except ``null`` is returned instead of ``false``. The OID being returned is different
from OIDs used by :ref:`type converter factories <converter-factories>` as it will be a primary key in ``pg_class``
system table containing rows for database relations, rather than in ``pg_type`` which contains type data.

Knowing the source table for a field can be quite helpful when transforming the result from an array to domain objects.

Configuring row format
======================

``public function setMode(int $mode = \PGSQL_ASSOC): $this``
    Sets how the returned rows are indexed. It accepts either ``PGSQL_ASSOC`` or ``PGSQL_NUM``
    (but not ``PGSQL_BOTH``) constants used by
    `pg_fetch_row() <https://www.php.net/manual/en/function.pg-fetch-row.php>`__.

    This affects rows returned either when iterating over the result object with ``foreach``
    or accessing the array offsets of it.

``public function setType(int|string $fieldIndex, mixed $type): $this``
    Explicitly sets the type converter for the result field.

    ``Result`` uses :ref:`the type converter factory <connecting-configuration>` used by the ``Connection``, so
    ``$type`` should be acceptable for that.

Using ``setMode()`` is straightforward:

.. code-block:: php

   $result = $connection->executeParams(
       'select article_id, article_title from articles where article_id = $1',
       [13]
   );

   $result->setMode(PGSQL_ASSOC);
   var_dump($result[0]);

   $result->setMode(PGSQL_NUM);
   var_dump($result[0]);

with the following output

.. code-block:: output

   array(2) {
     'article_id' =>
     int(13)
     'article_title' =>
     string(37) "Abusing sad-spirit/pg-wrapper package"
   }
   array(2) {
     [0] =>
     int(13)
     [1] =>
     string(37) "Abusing sad-spirit/pg-wrapper package"
   }

It is not generally needed to use ``setType()`` as proper converters are deduced from result metadata,
the exception is :ref:`columns defined by row constructor <queries-result>`.

Getting the whole result as array
=================================

``public function fetchColumn(int|string $fieldIndex): array``
    Returns an array containing all values from a given column in the result set. ``$fieldIndex`` can be either a
    column name or its 0-based numeric index.

``public function fetchAll(?int $mode = null, int|string|null $keyColumn = null, bool $forceArray = false, bool $group = false): array``
    Returns an array containing all rows of the result set.

    - ``$mode`` can be either ``PGSQL_ASSOC`` or ``PGSQL_NUM`` constant specifying how the rows are indexed.
      If ``null``, defaults to one set by ``setMode()``.
    - ``$keyColumn`` can be either a column name or its 0-based numeric index. If given, values of this column
      will be used as keys in the outer array.
    - ``$forceArray`` is only useful if ``$keyColumn`` is specified and the query returns exactly two columns.
      If ``false`` an array of the form ``key column value => other column value`` is returned.
      If ``true`` the values will be one element arrays with other column's values, instead of values directly.
    - ``$group`` is useful when ``$keyColumn`` is specified and its values may be non-unique.
      If ``true``, the values in the returned array are wrapped in another array. If there are duplicate values in
      key column, values of other columns will be appended to this array instead of overwriting previous ones.


``fetchColumn()`` is straightforward as well as ``fetchAll()`` with default arguments:

.. code-block:: php

   $result = $connection->execute('select article_id, article_title from articles order by article_id');
   var_dump($result->fetchAll());
   var_dump($result->fetchColumn('article_title'));

will output

.. code-block:: output

   array(2) {
     [0] =>
     array(2) {
       'article_id' =>
       int(12)
       'article_title' =>
       string(35) "Using sad-spirit/pg-wrapper package"
     }
     [1] =>
     array(2) {
       'article_id' =>
       int(13)
       'article_title' =>
       string(37) "Abusing sad-spirit/pg-wrapper package"
     }
   }
   array(2) {
     [0] =>
     string(35) "Using sad-spirit/pg-wrapper package"
     [1] =>
     string(37) "Abusing sad-spirit/pg-wrapper package"
   }

Using the ``$keyColumn`` argument with ``fetchAll()`` is a bit more tricky:

.. code-block:: php

    $result = $connection->execute("select * from (values (1, 'one'), (2, 'two'), (2, 'three')) as v (id, name)");

    echo "Default \$forceArray and \$group:\n";
    var_dump($result->fetchAll(keyColumn: 'id'));
    echo "\n\$forceArray = true:\n";
    var_dump($result->fetchAll(keyColumn: 0, forceArray: true));
    echo "\n\$group = true:\n";
    var_dump($result->fetchAll(keyColumn: 'id', group: true));
    echo "\nexplicit mode, \$forceArray = true, \$group = true:\n";
    var_dump($result->fetchAll(\PGSQL_NUM, 0, true, true));

outputs

.. code-block:: output

    Default $forceArray and $group:
    array(2) {
      [1]=>
      string(3) "one"
      [2]=>
      string(5) "three"
    }

    $forceArray = true:
    array(2) {
      [1]=>
      array(1) {
        ["name"]=>
        string(3) "one"
      }
      [2]=>
      array(1) {
        ["name"]=>
        string(5) "three"
      }
    }

    $group = true:
    array(2) {
      [1]=>
      array(1) {
        [0]=>
        string(3) "one"
      }
      [2]=>
      array(2) {
        [0]=>
        string(3) "two"
        [1]=>
        string(5) "three"
      }
    }

    explicit mode, $forceArray = true, $group = true:
    array(2) {
      [1]=>
      array(1) {
        [0]=>
        array(1) {
          [0]=>
          string(3) "one"
        }
      }
      [2]=>
      array(2) {
        [0]=>
        array(1) {
          [0]=>
          string(3) "two"
        }
        [1]=>
        array(1) {
          [0]=>
          string(5) "three"
        }
      }
    }

Custom iterators
================

All the below functions are generator ones, using ``yield`` to return rows.

``public function iterateColumn(int|string $fieldIndex): \Traversable``
    Returns an iterator over a single column of the result. ``$fieldIndex`` is either a column name or its 0-based
    numeric index.

    Unless you really need an array of column values, it is recommended to use this rather than ``fetchColumn()``, as
    it doesn't have to populate an array.

``public function iterateAssociative(): \Traversable``
    Returns an iterator over result with values representing result rows as associative arrays.

    This is similar to calling ``$result->setMode(PGSQL_ASSOC)`` and then iterating over ``$result`` with ``foreach``.

``public function iterateNumeric(): \Traversable``
    Returns an iterator over result with values representing result rows as enumerated arrays.

    This is similar to calling ``$result->setMode(PGSQL_NUM)`` and then iterating over ``$result`` with ``foreach``.

``public function iterateKeyedAssociative(?string $keyColumn = null, bool $forceArray = false): \Traversable``
    Returns an iterator over result with keys corresponding to the values of the given column and values
    representing either the values of the remaining column or the rest of the columns as associative arrays.

    ``$keyColumn`` is the column name, if ``null`` then the first column will be used. ``$forceArray`` is applicable
    when the query returns exactly two columns. If ``false``, the other column's values will be returned directly,
    if ``true`` they will be wrapped in an array keyed with the column name.

``public function iterateKeyedNumeric(int $keyColumn = 0, bool $forceArray = false): \Traversable``
    Returns an iterator over result with keys corresponding to the values of the column with the given index and
    values representing either the values of the remaining column or the rest of the columns as enumerated arrays.

    ``$keyColumn`` is the 0-based numeric index. ``$forceArray`` is applicable when the query returns exactly
    two columns. If ``false`` the other column's values will be returned directly, if ``true`` they will be
    wrapped in an array.

It is recommended to use ``iterateKeyedAssociative()`` and ``iterateKeyedNumeric()`` instead of ``fetchAll()`` with
``$keyColumn`` specified, unless you really need the array returned by the latter. If you just need to iterate,
the behaviour is similar, note the duplicate keys, though:

.. code-block:: php

    $result = $connection->execute("select * from (values (1, 'one'), (2, 'two'), (2, 'three')) as v (id, name)");

    echo "iterateKeyedAssociative(): \n";
    foreach ($result->iterateKeyedAssociative('id') as $k => $v) {
        echo "$k => $v\n";
    }
    echo "\nfetchAll(): \n";
    foreach ($result->fetchAll(keyColumn: 'id') as $k => $v) {
        echo "$k => $v\n";
    }

results in

.. code-block:: output

    iterateKeyedAssociative():
    1 => one
    2 => two
    2 => three

    fetchAll():
    1 => one
    2 => three
