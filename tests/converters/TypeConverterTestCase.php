<?php

/*
 * This file is part of sad_spirit/pg_wrapper:
 * converter of complex PostgreSQL types and an OO wrapper for PHP's pgsql extension.
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\tests\converters;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use sad_spirit\pg_wrapper\TypeConverter;

/**
 * Base class for type converter tests
 *
 * @template T of TypeConverter
 */
abstract class TypeConverterTestCase extends TestCase
{
    /**
     * @var T
     */
    protected TypeConverter $converter;

    /**
     * Tests conversion from native Postgres format to PHP types
     *
     * @param string|null $native String representation of value in the database
     * @param mixed       $value  Conversion result or an instance of expected throwable
     */
    #[DataProvider('valuesBoth')]
    #[DataProvider('valuesFrom')]
    public function testCastFrom(?string $native, mixed $value): void
    {
        if ($value instanceof \Throwable) {
            $this->expectException($value::class);
            $this->converter->input($native);
        } else {
            $this->assertEquals($value, $this->converter->input($native));
        }
    }

    /**
     * Tests conversion from PHP types to native Postgres format
     *
     * @param string|null|\Throwable $native String representation of value in the database or an instance of expected
     *                                       throwable
     * @param mixed                  $value  Incoming PHP value
     */
    #[DataProvider('valuesBoth')]
    #[DataProvider('valuesTo')]
    public function testCastTo(string|\Throwable|null $native, mixed $value): void
    {
        if ($native instanceof \Throwable) {
            $this->expectException($native::class);
            $this->converter->output($value);
        } else {
            $this->assertEquals($native, $this->converter->output($value));
        }
    }

    /**
     * Provides data used in both testCastTo() and testCastFrom()
     * @return array<int, array<int, mixed>>
     */
    abstract public static function valuesBoth(): array;

    /**
     * Provides data used in testCastFrom() only
     * @return array<int, array<int, mixed>>
     */
    abstract public static function valuesFrom(): array;

    /**
     * Provides data used in testCastTo() only
     * @return array<int, array<int, mixed>>
     */
    abstract public static function valuesTo(): array;
}
