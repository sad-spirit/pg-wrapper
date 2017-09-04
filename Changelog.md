# Changelog

## 0.2.0

* Removed `MetadataCache` interface and its implementations. The package can now use any PSR-6 compatible cache implementation for metadata.
* `TypeConverterFactory` is now an interface. Previous class is renamed `converters\DefaultTypeConverterFactory` and implements said interface.
* Features depending on classes from `sad_spirit\pg_builder` package were removed from `DefaultTypeConverterFactory`, will now be available in `sad_spirit\pg_builder`.
* Added possibility to cache composite types' structure and automatically load structure of free-standing composite types.
* Added `converters\StubConverter` and `converters\StubTypeConverterFactory` to be able to essentially switch off type conversion. Instance of the former is also now returned instead of `converters\StringConverter` when a proper converter cannot be determined.
* `converters\containers\CompositeConverter` changed to not use `each()` construct deprecated in PHP 7.2 

## 0.1.0

Initial release on GitHub