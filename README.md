# sad_spirit\pg_wrapper

> Note: master branch contains code for the upcoming 3.0 version that requires PHP 8.2+
>
> [Branch 2.x](../../tree/2.x) contains the stable version compatible with PHP 7.2+


[![Build Status](https://github.com/sad-spirit/pg-wrapper/actions/workflows/ci.yml/badge.svg?branch=master)](https://github.com/sad-spirit/pg-wrapper/actions/workflows/ci.yml)

[![Static Analysis](https://github.com/sad-spirit/pg-wrapper/actions/workflows/static-analysis.yml/badge.svg?branch=master)](https://github.com/sad-spirit/pg-wrapper/actions/workflows/static-analysis.yml)

This package has two parts and purposes
* Converter of [PostgreSQL data types](https://www.postgresql.org/docs/current/datatype.html) to their PHP equivalents and back and
* An object-oriented wrapper around PHP's native [pgsql extension](https://php.net/manual/en/book.pgsql.php).

While the converter part can be used separately e.g. with [PDO](https://www.php.net/manual/en/book.pdo.php), 
features like transparent conversion of query results work only with the wrapper.

## Why type conversion?

PostgreSQL supports a large (and extensible) set of complex database types: arrays, ranges, geometric and date/time
types, composite (row) types, JSON...

```SQL
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
```

Unfortunately neither of PHP extensions for talking to PostgreSQL (pgsql and PDO_pgsql) can map these complex
types to their PHP equivalents. They return string representations instead: both
```PHP
var_dump(pg_fetch_assoc(pg_query($conn, 'select * from test')));
```
and
```PHP
var_dump($pdo->query('select * from test')->fetch(\PDO::FETCH_ASSOC));
```
yield
```
array(5) {
  ["strings"]=>
  string(28) "{"Mary had","a little lamb"}"
  ["coords"]=>
  string(13) "(55.75,37.61)"
  ["occupied"]=>
  string(23) "[2014-01-13,2014-09-19)"
  ["age"]=>
  string(13) "8 mons 6 days"
  ["document"]=>
  string(50) "{"title":"pg_wrapper","text":"pg_wrapper is cool"}"
}
```

And that is where this library kicks in:
```PHP
$result = $connection->execute('select * from test');
var_dump($result[0]);
```
yields
```
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
```
Note that no configuration is needed here: proper types are deduced from metadata returned with the result.

## Why another wrapper when we have PDO, Doctrine DBAL, etc?

The goal of an abstraction layer is to target the Lowest Common Denominator, and thus it intentionally hides some low-level
APIs that we can use with the native extension and / or adds another level of complexity.

* PDO does not expose [`pg_query_params()`](http://php.net/manual/en/function.pg-query-params.php), so you have
  to `prepare()` / `execute()` each query even if you `execute()` it only once. Doctrine DBAL has `Connection::executeQuery()`
  but it uses `prepare()` / `execute()` under the hood.
* Postgres only supports `$1` positional parameters natively, while PDO has positional `?` and named `:foo` parameters.
  PDO rewrites the query to convert the latter to the former, shortcomings in that rewrite logic 
  prevented using [Postgres operators containing `?`](https://www.postgresql.org/docs/current/functions-json.html#FUNCTIONS-JSONB-OP-TABLE) with
  PDO [until PHP 7.4](https://wiki.php.net/rfc/pdo_escape_placeholders) and led to problems when using dollar quoting for strings
  [until PHP 8.4](https://www.php.net/manual/en/migration84.new-features.php#migration84.new-features.pdo-pgsql).
* PDO has an *extremely inefficient* way to work with result metadata in Postgres. Its 
  [`PDOStatement::getColumnMeta()`](https://www.php.net/manual/en/pdostatement.getcolumnmeta.php)
  executes one to two database queries for each call.

### Parameter as array: native vs. abstraction

A very common problem for database abstraction is providing a list of parameters to a query with an `IN` clause
```SQL
SELECT * FROM stuff WHERE id IN (?)
```
where `?` actually represents a variable number of parameters.

On the one hand, if you don't need the abstraction, then Postgres has native array types,
and this can be easily achieved with the following query
```SQL
-- in case of using PDO just replace $1 with a PDO-compatible placeholder
SELECT * FROM stuff WHERE id = ANY($1::INTEGER[])
```
passing an array literal as its parameter value
```PHP
use sad_spirit\pg_wrapper\converters\DefaultTypeConverterFactory;

$arrayLiteral = (new DefaultTypeConverterFactory())
    ->getConverterForTypeSpecification('INTEGER[]')
    ->output([1, 2, 3]);
```
Obviously, the above query can be prepared as usual and executed with another array literal.

On the other hand, Doctrine DBAL [has its own solution for parameter lists](https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/data-retrieval-and-manipulation.html#list-of-parameters-conversion)
which once again depends on rewriting SQL and does not work with `prepare()` / `execute()`. It also has ["support" for array
types](https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/types.html#array-types), 
but that just (un)serializes PHP arrays rather than converts them from/to native DB representation. 
Serialized PHP arrays will obviously not work with the above query.

## Installation

Require the package with composer:
```
composer require "sad_spirit/pg_wrapper:^3"
```
pg_wrapper requires at least PHP 8.2. Native [pgsql extension](https://php.net/manual/en/book.pgsql.php)
should be enabled to use classes that access the DB (the extension is not a hard requirement).

Minimum supported PostgreSQL version is 12

It is highly recommended to use [PSR-6 compatible](https://www.php-fig.org/psr/psr-6/) metadata cache in production
to prevent possible metadata lookups from database on each page request.

## Documentation

For in-depth description of package features, visit [pg_wrapper manual](https://pg-wrapper.readthedocs.io/).
