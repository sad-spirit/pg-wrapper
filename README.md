# sad_spirit\pg_wrapper

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
types to their PHP equivalents. They return string representations instead:

```PHP
var_dump(pg_fetch_assoc(pg_query($conn, 'select * from test')));
```
yields
```
array(5) {
  'strings' =>
  string(28) "{"Mary had","a little lamb"}"
  'coords' =>
  string(13) "(55.75,37.61)"
  'occupied' =>
  string(23) "[2014-01-13,2014-09-19)"
  'age' =>
  string(13) "8 mons 6 days"
  'document' =>
  string(50) "{"title":"pg_wrapper","text":"pg_wrapper is cool"}"
}
```

And that is where this library kicks in:
```
$result = $connection->execute('select * from test');
var_dump($result[0]);
```
yields
```
array(5) {
  'strings' =>
  array(2) {
    [0] =>
    string(8) "Mary had"
    [1] =>
    string(13) "a little lamb"
  }
  'coords' =>
  class sad_spirit\pg_wrapper\types\Point#18 (1) {
    private $_coordinates =>
    array(2) {
      'x' =>
      double(55.75)
      'y' =>
      double(37.61)
    }
  }
  'occupied' =>
  class sad_spirit\pg_wrapper\types\DateTimeRange#19 (1) {
    ...
  }
  'age' =>
  class sad_spirit\pg_wrapper\types\DateInterval#22 (16) {
    ...
  }
  'document' =>
  array(2) {
    'title' =>
    string(10) "pg_wrapper"
    'text' =>
    string(18) "pg_wrapper is cool"
  }
}
```


## Requirements

pg_wrapper depends on pgsql extension. The reason for using that instead of PDO pgsql driver is that the latter does
not support something like `pg_query_params()`.

It is highly recommended to use metadata cache in production to prevent metadata lookups from database on each request.