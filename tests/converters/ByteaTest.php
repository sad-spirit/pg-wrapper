<?php

/**
 * Converter of complex PostgreSQL types and an OO wrapper for PHP's pgsql extension
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-wrapper/master/LICENSE
 *
 * @package   sad_spirit\pg_wrapper
 * @copyright 2014-2024 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
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
            ['\x41$2',                 new TypeConversionException()]
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
