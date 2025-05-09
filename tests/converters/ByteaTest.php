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
use sad_spirit\pg_wrapper\{
    converters\ByteaConverter,
    exceptions\TypeConversionException
};

/**
 * Unit test for bytea type converter
 */
class ByteaTest extends TestCase
{
    protected ByteaConverter $caster;

    protected function setUp(): void
    {
        $this->caster = new ByteaConverter();
    }

    /**
     * ByteaConverter should only accept strings
     */
    #[DataProvider('invalidPHPValuesProvider')]
    public function testInvalidPHPValue(mixed $value): void
    {
        $this::expectException(TypeConversionException::class);
        $this->caster->output($value);
    }

    #[DataProvider('getValuesFrom')]
    public function testCastFrom(?string $native, string|\Throwable|null $value): void
    {
        if ($value instanceof \Throwable) {
            $this->expectException($value::class);
        }
        $this->assertEquals($value, $this->caster->input($native));
    }

    #[DataProvider('getValuesToHex')]
    public function testCastToHex(?string $value, ?string $hexEncoded): void
    {
        $this->assertEquals($hexEncoded, $this->caster->output($value));
    }

    public static function getValuesFrom(): array
    {
        return [
            [null,                     null],
            ['',                       ''],
            ['\x  ',                   ''],
            ['abc\\000\'\\\\\\001def', "abc\000'\\\001def"],
            ['\x4142000102',           "AB\000\001\002"],
            ['\x4 1',                  new TypeConversionException()],
            ['\x41$2',                 new TypeConversionException()],

            // whitespace handling
            ["\\x 41\r\n42\t00\v01\f02", "AB\000\001\002"]
        ];
    }

    public static function getValuesToHex(): array
    {
        return [
            [null,         null],
            ['',           '\x'],
            ["\000'\\",    '\x00275c'],
            ['ABC',        '\x414243']
        ];
    }

    public static function invalidPHPValuesProvider(): array
    {
        return [
            [[]],
            [1.234],
            [new \stdClass()]
        ];
    }
}
