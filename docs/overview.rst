
========
Overview
========

This package is not a DB abstraction layer even though it may look similar (and draw some inspiration from projects
like `Doctrine/DBAL <https://www.doctrine-project.org/projects/dbal.html>`__,
`laminas-db <https://docs.laminas.dev/laminas-db/>`__, and `PEAR::MDB2 <https://pear.php.net/package/MDB2>`__).
Its goal is to leverage PostgreSQL's strengths in PHP projects rather than assist with building
RDBMS-agnostic applications.

One of such strengths is a `rich and extensible data type
system <https://www.postgresql.org/docs/current/interactive/datatype.html>`__,
so pg_wrapper allows to

- Easily build string representations for query parameters,
- Automatically convert query result columns from string representations to native PHP types,
- Use provided classes to represent PostgreSQL's complex types (geometric, ranges, etc.)
  that do not have native PHP equivalents,
- Add custom converters for custom and ad-hoc types.

Lets try using several of the more complex types

.. code-block:: postgres

    create table test (
        strings  text[],
        coords   point,
        occupied daterange,
        age      interval,
        document json
    );

    insert into test values (
        array['Mary had', 'a little lamb'], point(55.75, 37.61),
        daterange('2014-01-13', '2014-09-19'), age('2014-09-19', '2014-01-13'),
        '{"title":"pg_wrapper","text":"pg_wrapper is cool"}'
    );

.. code-block:: php

    use sad_spirit\pg_wrapper\Connection;

    var_dump(
        (new Connection('host=localhost dbname=test'))
            ->execute('select * from test')
            ->current()
    );

With no configuration needed for result types this outputs

.. code-block:: output

    array(5) {
      ["strings"]=>
      array(2) {
        [0]=>
        string(8) "Mary had"
        [1]=>
        string(13) "a little lamb"
      }
      ["coords"]=>
      object(sad_spirit\pg_wrapper\types\Point)#28 (2) {
        ["x"]=>
        float(55.75)
        ["y"]=>
        float(37.61)
      }
      ["occupied"]=>
      object(sad_spirit\pg_wrapper\types\DateTimeRange)#29 (5) {
        ["lower"]=>
        object(DateTimeImmutable)#30 (3) {
          ["date"]=>
          string(26) "2014-01-13 00:00:00.000000"
          ...
        }
        ["upper"]=>
        object(DateTimeImmutable)#31 (3) {
          ["date"]=>
          string(26) "2014-09-19 00:00:00.000000"
          ...
        }
      }
      ["age"]=>
      object(DateInterval)#32 (10) {
        ...
        ["m"]=>
        int(8)
        ["d"]=>
        int(6)
        ...
      }
      ["document"]=>
      array(2) {
        ["title"]=>
        string(10) "pg_wrapper"
        ["text"]=>
        string(18) "pg_wrapper is cool"
      }
    }

We can also convert the parameter values for a parametrized query

.. code-block:: php

    foreach (
        (new Connection('host=localhost dbname=test'))
            ->executeParams(
                'select * from pg_catalog.pg_type where oid = any($1::oid[]) order by typname',
                [[16, 20, 603]],
                ['oid[]']
            )
            ->iterateColumn('typname') as $name
    ) {
        echo $name . "\n";
    }

outputting

.. code-block:: output

    bool
    box
    int8

This did require specifying the type, but allowed passing an array for a query parameter.

Requirements
============

pg_wrapper requires at least PHP 8.2 with `ctype <https://www.php.net/manual/en/book.ctype.php>`__ and
`json <https://www.php.net/manual/en/book.json.php>`__ extensions (those are usually installed and enabled by default).

Native `pgsql <https://php.net/manual/en/book.pgsql.php>`__ extension (*not* PDO_pgsql) should be enabled
to use classes that access the DB, the extension is not a hard requirement.

Minimum supported PostgreSQL version is 12.

It is highly recommended to use `PSR-6 <https://www.php-fig.org/psr/psr-6/>`__ compatible metadata cache in production
to prevent possible metadata lookups from database on each page request.

Installation
============

Require the package with `composer <https://getcomposer.org/>`__:

.. code-block:: bash

    composer require "sad_spirit/pg_wrapper:^3"


Related packages
================

`sad_spirit/pg_builder <https://github.com/sad-spirit/pg-builder>`__
  A query builder for Postgres that contains
  a partial reimplementation of SQL parser used in Postgres itself. It can extract types info from SQL typecasts and
  propagate that to pg_wrapper when executing built queries.

`sad_spirit/pg_gateway <https://github.com/sad-spirit/pg-gateway>`__
  Builds upon pg_wrapper and pg_builder to provide
  `Table Data Gateway <https://martinfowler.com/eaaCatalog/tableDataGateway.html>`__ implementation
  for Postgres that allows

  - Transparent conversion of PHP types to Postgres types and back, both for query parameters and results;
  - Writing parts of the query as SQL strings and processing them as ASTs, e.g. combining queries
    generated via several gateways through ``WITH`` / ``JOIN`` / ``EXISTS()``.
