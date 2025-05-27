# Changelog

## [3.1.0] - 2025-05-27

### Added
 * `converters\ConfigurableTypeConverterFactory` interface that defines the public methods that were previously
   specific to `converters\DefaultTypeConverterFactory`. The new interface should be used for type hinting instead
   of the latter class.

### Changed
 * `converters\DefaultTypeConverterFactory::getConverterForQualifiedName()` method that is now defined in
   `ConfigurableTypeConverterFactory` is no longer marked `@internal`, its `$isArray` argument is now deprecated.

### Fixed
 * `converters\DefaultTypeConverterFactory::getConverterForTypeOID()` will return a converter that was explicitly
   registered for a complex (e.g. composite) type instead of the generic one,
   see [issue #14](https://github.com/sad-spirit/pg-wrapper/issues/14).


## [3.0.0] - 2025-02-25

Upgraded psalm, fixed issues found by `strictBinaryOperands`

## [3.0.0-beta.2] - 2025-02-02

Package manual is now published on [Read the Docs](https://pg-wrapper.readthedocs.io)

### Added
 * `converters\EnumConverter` class: converts values of Postgres `ENUM` type to PHP's string-backed enum and back.
 * It is now possible to pass classname of `types\Range` / `types\MultiRange` subclass to 
   `converters\containers\RangeConverter` / `converters\containers\MultiRangeConverter`
   constructor. The converter will return instances of the passed subclass from its `input()` method.

### Changed
 * `nextChar()` and `expectChar()` methods were moved from `converters\BaseConverter`
   to `converters\ContainerConverter` as they were only used by subclasses of the latter.
 * `TypeConverterFactory` interface now extends `converters\ConnectionAware`, thus its
   `setConnection()` method has a return type of `void`.

### Removed
 * `types\DateInterval` class: it only contained a static `formatAsISO8601()` method which was moved to
   `converters\datetime\IntervalConverter`. `IntervalConverter::input()` will now return
   an instance of native `\DateInterval`.

### Fixed
 * Upgraded phpstan to version 2 and psalm to version 6, fixed newly discovered issues.
 * `types\PointList` and `types\MultiRange` can have only 
    [list-compatible array offsets](https://www.php.net/manual/en/function.array-is-list.php),
    previously it was possible to make them have any offsets by passing an unpacked associative array
    to constructors.
 * `types\MultiRange` and its subclasses are now `readonly` as other classes representing complex types
 * `converters\NumericConverter` is used instead of `converters\FloatConverter` for `money` type values
   to prevent loss of precision.

## [3.0.0-beta] - 2024-12-21

The package now requires PHP 8.2+ and Postgres 12+. BC breaks are possible due to new language features being used,
please consult the [upgrade instructions](./UPGRADE.md).

### Added
 * Tested on PHP 8.4 and Postgres 17
 * Types data and error codes updated for Postgres 17.
 * `decorators\ConnectionDecorator` and `decorators\PreparedStatementDecorator` abstract base classes that
   can be used to add functionality to `Connection` and `PreparedStatement`. The base classes only forward calls 
   to wrapped instances of `Connection` and `PreparedStatement`.
 * `decorators\logging\Connection` and `decorators\logging\PreparedStatement` classes that extend the above and
   log all queries using PSR-3 compatible logger.
 * Iterator methods for `Result`:
   * `iterateColumn(int|string $fieldIndex)` - returns an iterator over a single column of the result, this complements
     `fetchColumn()`;
   * `iterateNumeric()` - returns an iterator over result with values representing result rows as enumerated arrays,
     this is similar to calling `$result->setMode(\PGSQL_NUM)` and iterating over `$result`, but more explicit;
   * `iterateAssociative()` -  returns an iterator over result with values representing result rows as associative
     arrays, this is once again a more explicit version of calling `$result->setMode(\PGSQL_ASSOC)`;
   * `iterateKeyedAssociative(?string $keyColumn = null, bool $forceArray = false)` - returns an iterator over result 
     with keys corresponding to the values of the given column and values representing either the values
     of the remaining column or the rest of the columns as associative arrays, this complements `fetchAll()` with
     key column specified and mode set to `\PGSQL_ASSOC`;
   * `iterateKeyedNumeric(int $keyColumn = 0, bool $forceArray = false)` - returns an iterator over result 
     with keys corresponding to the values of the column with the given index and values representing either 
     the values of the remaining column or the rest of the columns as enumerated arrays, this complements `fetchAll()`
     with key column specified and mode set to `\PGSQL_NUM`.

### Changed
 * Classes representing complex PostgreSQL types contain native `public readonly` properties rather than magic ones.
 * When parsing database values, consistently follow Postgres 17 in what is considered a whitespace character:
  space, `\r`, `\n`, `\t`, `\v`, `\f`.
 * SQL error codes are represented by cases of `SqlState` enum instead of `ServerException` class constants.
 * Added typehints for arguments and return values where not previously possible: e.g. `int|string` for
   methods dealing with OID values and `int|string` for methods of `Result` accepting field name / index.
 * `PreparedStatement` will fetch data on query parameters from DB by default. This behaviour can be changed via
   static `PreparedStatement::setAutoFetchParameterTypes(bool $autoFetch)` method.

### Removed
Features deprecated in 2.x releases were removed, see the [upgrade instructions](./UPGRADE.md) for the full list.

### Fixed
* An exception message for invalid array bounds in `ArrayConverter` got these bounds mixed.
* Proper casing of native `PgSql` namespace.

## [2.5.0] - 2024-09-12

### Fixed
 * `converters\datetime\IntervalConverter::output()` could incorrectly format the passed `float`-typed value.
 * `$block` property of `types\Tid` class no longer has an `int` typehint: as was the case with OIDs, block number
   is an *unsigned* 32-bit integer and may be out of range of PHP's *signed* `int` type on 32-bit builds.

### Deprecated
 * Getters / issers methods of classes representing complex PostgreSQL types. Use corresponding magic properties,
   these will be reimplemented as native `public readonly` properties in the next major version. Specifically,
   * `types\Circle`: `getCenter()` -> `$center`, `getRadius()` -> `$radius`;
   * `types\Line`: `getA()` -> `$A`, `getB()` -> `$B`, `getC()` -> `$C`;
   * `types\Path`: `isOpen()` -> `$open`;
   * `types\Point`: `getX()` -> `$x`, `getY()` -> `$y`;
   * `types\Box` and `types\LineSegment`: `getStart()` -> `$start`, `getEnd()` -> `$end`,
   * `types\Range`: `getLower()` -> `$lower`, `getUpper()` -> `$upper`, `isLowerInclusive()` -> `$lowerInclusive`,
      `isUpperInclusive()` -> `$upperInclusive`, `isEmpty()` -> `$empty`;
   * `types\Tid`: `getBlock()` -> `$block`, `getTuple()` -> `$tuple`.
 * `types\ReadOnlyProperties` trait used to implement the above magic readonly properties, as migration to native ones 
   is planned.
 * `converters\BaseConverter::getStrspn()` method that is no longer used in the package.

## [2.4.1] - 2024-06-19

### Added
 * `IntegerVectorConverter` for `int2vector` and `oidvector` types used in system catalogs

### Fixed
 * String representations of arrays which include dimensions (e.g. `[0:0]={1}`) are now supported
   (see [issue #12](https://github.com/sad-spirit/pg-wrapper/issues/12)). 
   An exception will be thrown if the structure of the resultant array does not match specified dimensions.
   If a lower bound is specified for a dimension, the keys in PHP array will start with that:
   `'[5:5]={1}'` will be converted to `[5 => 1]`.
 * Arrays of elements that have a delimiter other than comma are supported. In particular,
   converting from / to `box[]` type, which uses semicolon, is possible. Converters for custom
   types having a non-comma delimiter may implement the new `CustomArrayDelimiter` interface.

## [2.4.0] - 2024-05-20

A major update for `PreparedStatement`'s API, multiple renames.

### Added
 * `$resultTypes` argument for `Connection::prepare()` and `PreparedStatement::__construct()` 
   that will be passed on to `Result` instances returned by the created `PreparedStatement` instance.
 * In `PreparedStatement` class:
   * `setResultTypes(array $resultTypes)` method used to configure `$resultTypes` for returned `Result` instances. 
   * `setParameterType(int $parameterNumber, mixed $type)` method that allows to specify the type for a parameter
     separately from the value.
   * `setNumberOfParameters(int $numberOfParameters)` method that sets the number of parameters used in the query.
     If specified, this will be used to validate the `$parameterNumber` arguments given to various methods 
     and keys in parameter values array. 
   * `executeParams(array $params)` method that will execute the prepared statement using only the values
     from the given `$params` array. An exception will be thrown if any parameter values were previously bound.
   * `fetchParameterTypes(bool $overrideExistingTypes = false)` method that will fetch types for statement
     parameters from the DB. It will also set the correct number of parameters.
   * Static `setAutoFetchParameterTypes(bool $autoFetch)` / `getAutoFetchParameterTypes(): bool` static methods
     that trigger automatically fetching the above data in `PreparedStatement`'s constructor.
     This behaviour is currently disabled by default.
   * Destructor that deallocates the prepared statement.
 * `Result::getTableOID($fieldIndex)` method that exposes the result of `pg_field_table()`
 * Tested on PHP 8.3

### Deprecated
 * `ResultSet` class, use `Result` instead.
 * `Connection::checkRollbackNotNeeded()` method, use `Connection::assertRollbackNotNeeded()` instead.
 * Method names mentioning `resource`, as pgsql extension in PHP 8.1+ no longer uses resources:
   * `Connection::getResource()` -- `Connection::getNative()` should be used instead,
   * `Result::getResource()` -- `Result::getNative()`,
   * `Result::createFromResultResource()` -- `Result::createFromReturnValue()`.
 * In `PreparedStatement` class:
   * Passing `$params` to `execute()` method, use the new `PreparedStatement::executeParams()`
   * Passing `$resultTypes` to `execute()` method.
     Use the constructor or `setResultTypes()` method to set these.
   * Not specifying types for parameters is deprecated for `bindValue()` / `bindParam()` and
     will result in an exception for the new `executeParams()`.

### Fixed
 * `PreparedStatement::execute()` mapped bound values to query parameters in the order of `bindValue()` / `bindParam()` calls,
   essentially ignoring the parameter numbers. 

## [2.3.0] - 2023-09-15

A stable release following release of Postgres 16. No code changes since beta.

## [2.3.0-beta] - 2023-08-30

Updated for Postgres 16.

### Added
When connected to Postgres 16+, `output()` method of numeric type converters will accept non-decimal integer literals 
and numeric literals with underscores for digit separators, allowing to use those as query parameter values.

## [2.2.0] - 2023-05-12

### Changed
* Code that deals with loading type data from DB / cache and mapping type names to type OIDs was extracted
  from `DefaultTypeConverterFactory` into a new `CachedTypeOIDMapper` class implementing a new `TypeOIDMapper` interface.
  `DefaultTypeConverterFactory` accepts an implementation of that interface via `setOIDMapper()` method, defaulting
  to the old implementation.

### Deprecated
* `setCompositeTypesCaching()` and `getCompositeTypesCaching()` methods of `DefaultTypeConverterFactory`.
  While they will continue to work if an instance of `CachedTypeOIDMapper` is used by the factory for OID mapping,
  their usage should be switched to the same methods of `CachedTypeOIDMapper` instance:
  ```php
  // before
  $factory->setCompositeTypesCaching(true);
  // after
  $factory->getOIDMapper()->setCompositeTypesCaching(true);
  ```

## [2.1.1] - 2023-05-10

### Fixed
* Added forgotten `declare(strict_types=1)` to the files containing PHP classes
* Added annotations to `ResultSet` that prevent psalm from triggering errors when iterating / getting offsets,
  see [psalm issue #9698](https://github.com/vimeo/psalm/issues/9698)

## [2.1.0] - 2023-04-10

### Added
 * Classes that represent complex Postgres types now implement `\JsonSerializable` interface 
   (see [request #11](https://github.com/sad-spirit/pg-wrapper/issues/11)).
 * `SQL_JSON_ITEM_CANNOT_BE_CAST_TO_TARGET_TYPE` error code added in Postgres 15
 * Tested on PHP 8.2 and Postgres 15

## [2.0.0] - 2021-12-31

Updated static analyzers, no code changes.

## [2.0.0-beta] - 2021-11-18

Updated for Postgres 14 and PHP 8.1. The major version is incremented due to a few BC breaks.

### Changed
* Methods working with OIDs (e.g. `TypeConverterFactory::getConverterForTypeOID()`) 
  no longer have `int` typehints: OIDs are *unsigned* 32-bit integers and 
  may be out of range of PHP's *signed* `int` type on 32-bit builds.
* `converters\ConnectionAware` interface now defines a `setConnection()` method accepting an instance of 
  `Connection` instead of `setConnectionResource()` method accepting an underlying resource. This was done to
  prevent the need for boilerplate code checking whether the passed value is a resource (before PHP 8.1) or
  an instance of `\Pgsql\Connection` (PHP 8.1+) in implementations of `ConnectionAware`.
* `ServerException::fromConnection()` now accepts an instance of `Connection` rather than an underlying resource,
  this was done mostly for the same reasons as above.
* `ConnectionException` extends `ServerException` so that it is now possible to specify `SQLSTATE` error code for it
  (e.g. `ServerException::ADMIN_SHUTDOWN`).

### Added
* As `pgsql` functions in PHP 8.1 now return and accept objects instead of resources, added checks for these objects 
  alongside `is_resource` checks for earlier PHP versions.
* Full support for multirange types added in Postgres 14, with `types\Multirange` and its descendants to
  represent the values on PHP side and `converters\containers\MultiRangeConverter` to transform the values
  to and from DB string representation.
* New error code defined in Postgres 14.
* Support for infinite values in `NumericConverter` (numeric type in Postgres 14 allows these).
* `Connection::getLastError()` method.

### Fixed
Missing bounds in range types are always marked as exclusive, this follows PostgreSQL's behaviour.

## [1.0.0] - 2021-06-26

### Changed
`ResultSet` caches the last fetched row: e.g. accessing `$result[0]['field2']` after `$result[0]['field1']`
will no longer cause an additional `pg_fetch_array()` call with subsequent type conversions

### Removed
`DefaultTypeConverterFactory` no longer registers converters for obsolete `abstime` and `reltime` types, those were
removed in Postgres 12

## [1.0.0-beta.4] - 2021-02-15

### Changed
* Objects representing Postgres types are now immutable.
* `ResultSet::current()` and `ResultSet::offsetGet()` will return `null` rather than `false` for non-existent offsets.
* `Connection::execute()`, `Connection::executeParams()`, `PreparedStatement::execute()` will now consistently return `ResultSet` instead of
  `ResultSet|int|bool`. Number of affected rows for DML queries is available via new `ResultSet::getAffectedRows()` method.
* `ext-pgsql` is a suggested dependency rather than required, so that type converters may be used with e.g. PDO

### Added
* Tested and supported on PHP 8
* Static analysis with phpstan and psalm
* Package `Exception` interface now extends `\Throwable`

### Fixed
* Lots of minor issues caught by static analysis
  * Added sanity checks to `BaseDateTimeConverter` when working with connection resource (e.g. it will no longer try to get `DateStyle` setting from closed connection).
  * Added tests for `FloatConverter` and `NumericConverter` that check behaviour with locales having `','` as a decimal separator.
  * `ByteaConverter` will reject non-strings.
  * `HstoreConverter` will properly error on invalid input.
  * Additional checks for `pg_*()` functions returning `false`.

## [1.0.0-beta.3] - 2020-09-18

### Changed
* Date and time converters will return `\DateTimeImmutable` instances instead of `\DateTime`.
* `DateTimeRange` bounds are now instances of `\DateTimeImmutable` instead of `\DateTime`.
  It accepts all `\DateTimeInterface` implementations for bounds but will convert them to `\DateTimeImmutable`.
* Disallow subsequent `setConnection()` calls on `TypeConverterFactory` implementations with
  different `Connection` instances.

### Fixed
* Date and time converters will accept instances of `\DateTimeInterface`, not just `\DateTime`.
* `StubConverter::output()` will convert a non-null value to string.
* `DefaultTypeConverterFactory::setConnection()` properly updates the existing converters. 

## [1.0.0-beta.2] - 2020-07-19

### Fixed
Namespaced `BadMethodCallException` now extends  SPL's `BadMethodCallException` rather than `InvalidArgumentException`

## [1.0.0-beta] - 2020-07-18

### Added
* Types array in `DefaultTypeConverterFactory` is pre-populated with built-in data types of PostgreSQL. Loading
  metadata from DB / cache will now only be needed if using custom types.
* `Connection::atomic()` method which accepts a callable and executes it atomically, opening a transaction
  and creating savepoints for nested `atomic()` calls as needed. It will automatically commit if callback executes 
  successfully and rollback if it throws an exception. It is possible to register callbacks to execute after 
  the final commit or rollback.
* `Connection::quoteIdentifier()` method.

### Changed
* Requires at least PHP 7.2
* Requires at least PostgreSQL 9.3
* Split `TypeConverterFactory::getConverter()` method into
  * `getConverterForTypeOID()` - returns a converter for the database type OID, used by `ResultSet` to
    find converters for result columns,
  * `getConverterForTypeSpecification()` - returns a converter for a user-supplied type specification
    (e.g. type name or array describing fields of the composite type).
* Removed `Connection::guessOutputFormat()`, added `TypeConverterFactory::getConverterForPHPValue()` instead.
  Made it possible to specify mapping between PHP classes and type converters in `DefaultTypeConverterFactory`
  so that its `getConverterForPHPValue()` implementation can process new custom types.
* Methods `beginTransaction()`, `commit()`, `rollback()` no longer work with savepoints, separate methods
  `createSavepoint()`, `releaseSavepoint()`, `rollbackToSavepoint()` added
* Renamed `InvalidQueryException` to `ServerException` and added several subclasses for that. If a query fails:
  * `ConnectionException` is thrown when connection to server failed.
  * Subclass of `ServerException` is thrown based on `SQLSTATE` error code when said code is available.

### Removed
* Support for `mbstring.func_overload`, which was deprecated in PHP 7.2
* `fsec` field from custom `DateInterval` class, as native `f` field for fractional seconds 
  is available since PHP 7.1.
  
### Fixed 
* Optimized type converter for interval fields quite a bit.
* Sub-converters of container converters (array, composite and range) that implement `ConnnectionAware` now
  properly receive the connection resource.
* `Connection::quote()` now uses recommended `pg_escape_literal()` under the hood.
* Connection is automatically established only the first time it is needed, not after an explicit `disconnect()` call.

## [0.2.2] - 2017-09-18

### Fixed
Constructor of `PreparedStatement` should skip `null` values `$paramTypes` like it did before 0.2.1

## [0.2.1] - 2017-09-12

### Added
* Manual `prepare()` / `deallocate()` methods to `PreparedStatement`
* Allow cloning a `Connection` object, but force opening a different connection.

### Removed
* Removed unused `BadMethodCallException` class

### Fixed
* `RangeConverter` could enter an infinite loop on some invalid input.
* Field index in `ResultSet` was checked against wrong property.
* Types specified for query parameters and results as `TypeConverter` instances will *always* be passed through `TypeConverterFactory::getConverter()` so that converters implementing `ConnectionAware` can be properly configured. Previous behaviour was inconsistent.
* When a `TypeConverter` instance is passed to `StubTypeConverterFactory::getConverter()` it will be returned instead of `StubConverter`. Converter implementing `ConnectionAware` will be configured with current connection, like with `DefaultTypeConverterFactory`.

## [0.2.0] - 2017-09-04

### Added
* It is now possible to cache composite types' structure and automatically load structure of free-standing composite types.
* New `converters\StubConverter` and `converters\StubTypeConverterFactory` allow to essentially switch off type conversion. Instance of the former is also now returned instead of `converters\StringConverter` when a proper converter cannot be determined.

### Changed
* Removed `MetadataCache` interface and its implementations. The package can now use any PSR-6 compatible cache implementation for metadata.
* `TypeConverterFactory` is now an interface. Previous class is renamed `converters\DefaultTypeConverterFactory` and implements said interface.
* Features depending on classes from `sad_spirit\pg_builder` package were removed from `DefaultTypeConverterFactory`, will now be available in `sad_spirit\pg_builder`.

### Fixed
* `converters\containers\CompositeConverter` changed to not use `each()` construct deprecated in PHP 7.2 

## [0.1.0] - 2014-09-28 

Initial release on GitHub

[0.1.0]: https://github.com/sad-spirit/pg-wrapper/releases/tag/v0.1.0
[0.2.0]: https://github.com/sad-spirit/pg-wrapper/compare/v0.1.0...v0.2.0
[0.2.1]: https://github.com/sad-spirit/pg-wrapper/compare/v0.2.0...v0.2.1
[0.2.2]: https://github.com/sad-spirit/pg-wrapper/compare/v0.2.1...v0.2.2
[1.0.0-beta]: https://github.com/sad-spirit/pg-wrapper/compare/v0.2.2...v1.0.0-beta
[1.0.0-beta.2]: https://github.com/sad-spirit/pg-wrapper/compare/v1.0.0-beta...v1.0.0-beta.2
[1.0.0-beta.3]: https://github.com/sad-spirit/pg-wrapper/compare/v1.0.0-beta.2...v1.0.0-beta.3
[1.0.0-beta.4]: https://github.com/sad-spirit/pg-wrapper/compare/v1.0.0-beta.3...v1.0.0-beta.4
[1.0.0]: https://github.com/sad-spirit/pg-wrapper/compare/v1.0.0-beta.4...v1.0.0
[2.0.0-beta]: https://github.com/sad-spirit/pg-wrapper/compare/v1.0.0...v2.0.0-beta
[2.0.0]: https://github.com/sad-spirit/pg-wrapper/compare/v2.0.0-beta...v2.0.0
[2.1.0]: https://github.com/sad-spirit/pg-wrapper/compare/v2.0.0...v2.1.0
[2.1.1]: https://github.com/sad-spirit/pg-wrapper/compare/v2.1.0...v2.1.1
[2.2.0]: https://github.com/sad-spirit/pg-wrapper/compare/v2.1.1...v2.2.0
[2.3.0-beta]: https://github.com/sad-spirit/pg-wrapper/compare/v2.2.0...v2.3.0-beta
[2.3.0]: https://github.com/sad-spirit/pg-wrapper/compare/v2.3.0-beta...v2.3.0
[2.4.0]: https://github.com/sad-spirit/pg-wrapper/compare/v2.3.0...v2.4.0
[2.4.1]: https://github.com/sad-spirit/pg-wrapper/compare/v2.4.0...v2.4.1
[2.5.0]: https://github.com/sad-spirit/pg-wrapper/compare/v2.4.1...v2.5.0
[3.0.0-beta]: https://github.com/sad-spirit/pg-wrapper/compare/v2.5.0...v3.0.0-beta
[3.0.0-beta.2]: https://github.com/sad-spirit/pg-wrapper/compare/v3.0.0-beta...v3.0.0-beta.2
[3.0.0]: https://github.com/sad-spirit/pg-wrapper/compare/v3.0.0-beta.2...v3.0.0
[3.1.0]: https://github.com/sad-spirit/pg-wrapper/compare/v3.0.0...v3.1.0
