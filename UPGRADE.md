# Upgrade to 3.0

## Removed features

* Custom `types\DateInterval` class. Its `formatAsISO8601()` static method was moved
  to `converters\datetime\IntervalConverter`, `input()` method of that converter now returns
  instances of native `\DateInterval` class.

Features deprecated in 2.x releases were removed, specifically
* `ResultSet` class -- use `Result`.
* Getter / "isser" methods of classes for complex PostgreSQL types, use the properties instead. The methods were
  needed previously to support magic readonly properties, now these properties are native `public readonly`
  and the following methods are no longer available:
    * `types\Circle`: `getCenter()`, `getRadius()`;
    * `types\Line`: `getA()`, `getB()`, `getC()`;
    * `types\Path`: `isOpen()`;
    * `types\Point`: `getX()`, `getY()`;
    * `types\Box` and `types\LineSegment`: `getStart()`, `getEnd()`;
    * `types\Range`: `getLower()`, `getUpper()`, `isLowerInclusive()`, `isUpperInclusive()`, `isEmpty()`;
    * `types\Tid`: `getBlock()`, `getTuple()`.
* `types\ReadOnlyProperties` trait previously used to implement magic readonly properties.
* `converters\BaseConverter::getStrspn()` method.
* Methods mentioning `resource`, as pgsql extension in PHP 8.1+ no longer uses resources:
    * `Connection::getResource()` -- use `Connection::getNative()`;
    * `Result::getResource()`, `Result::createFromResultResource()` -- use `Result::getNative()` and `Result::createFromReturnValue()`.
* Methods of `converters\DefaultTypeConverterFactory` that are now part of `converters\TypeOIDMapper` interface or
  `converters\CachedTypeOIDMapper` class:
    * `findTypeNameForOID()` / `findOIDForTypeName()`;
    * `isArrayTypeOID()` / `isRangeTypeOID()` / `isMultiRangeTypeOID()` / `isDomainTypeOID()` /
      `isCompositeTypeOID()` / `isBaseTypeOID()`;
    * `setCompositeTypesCaching()` / `getCompositeTypesCaching()`.
* `Connection::checkRollbackNotNeeded()` method -- use `Connection::assertRollbackNotNeeded()`.
* `$params` and `$resultTypes` arguments of `PreparedStatement::execute()`. It will now execute the statement using only
  the previously bound values and passing previously specified result types to `Result`. 

## BC breaks due to PHP version bump

### SQL error codes

These are now represented by cases of `SqlState` enum instead of `ServerException` class constants.
Before: 
```PHP
try {
    // ...
} catch (ServerException $e) {
    if ($e::UNIQUE_VIOLATION === $e->getSqlState()) {
        // handle unique violation
    }
}
```
now 
```PHP
try {
    // ...
} catch (ServerException $e) {
    if (SqlState::UNIQUE_VIOLATION === $e->getSqlState()) {
        // handle unique violation
    }
}
```

### `Range` constructor
Constructor of classes representing range types, backed by `types\RangeConstructor` interface, now has
the fifth `bool $empty = false` argument used to create empty ranges
```PHP
$emptyRange = new NumericRange(empty: true);
```
Custom subclasses of `types\Range` should be updated accordingly. `Range::createEmpty()` still works:
```PHP
$emptyRange = NumericRange::createEmpty();
```

### Additional typehints

Typehints were added for method arguments and return values where not previously possible or omitted, specifically 
* `int|string` typehints for methods dealing with OID values:
  * `$oid` argument of `TypeConverterFactory::getConverterForTypeOID()` and its implementations,
  * Return value of `Result::getTableOID()`,
  * Return value of `converters\TypeOIDMapper::findOIDForTypeName()` and its implementations,
  * `$oid` argument of `findTypeNameForOID()`, `isBaseTypeOID()`, and `isCompositeTypeOID()` methods
    in `converters\TypeOIDMapper` and their implementations,
  * `$oid` and `$baseTypeOid` arguments for `isArrayTypeOID()`, `isDomainTypeOID()`, `isRangeTypeOID()`, and
    `isMultiRangeTypeOID()` methods in `converters\TypeOIDMapper` and their implementations;
*  `int|string` typehints for `$fieldIndex` arguments of `setType()`, `fetchColumn()`, and `getTableOID()` methods
   in `Result` class;
* `int|string|null` typehint for `$keyColumn` argument of `Result::fetchAll()`. 
* `static` typehint for return value of `types\ArrayRepresentable::createFromArray()`
