# Changelog

## [Unreleased]

### Changed
* Objects representing Postgres types are now immutable.
* `ResultSet::current()` and `ResultSet::offsetGet()` will return `null` rather than `false` for non-existent offsets.
* `Connection::execute()`, `Connection::executeParams()`, `PreparedStatement::execute()` will now consistently return `ResultSet` instead of
  `ResultSet|int|bool`. Number of affected rows for DML queries is available via new `ResultSet::getAffectedRows()` method.

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
[Unreleased]: https://github.com/sad-spirit/pg-wrapper/compare/v1.0.0-beta.3...HEAD