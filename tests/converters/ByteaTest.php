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
    /**
     * @var ByteaConverter
     */
    protected $caster;

    protected function setUp(): void
    {
        $this->caster = new ByteaConverter();
    }

    /**
     * ByteaConverter should only accept strings
     *
     * @param mixed $value
     * @dataProvider invalidPHPValuesProvider
     */
    public function testInvalidPHPValue($value): void
    {
        $this::expectException(TypeConversionException::class);
        $this->caster->output($value);
    }

    /**
     * @dataProvider getValuesFrom
     * @param string|null            $native
     * @param string|null|\Throwable $value
     */
    public function testCastFrom(?string $native, $value): void
    {
        if ($value instanceof \Throwable) {
            $this->expectException(\get_class($value));
        }
        $this->assertEquals($value, $this->caster->input($native));
    }

    /**
     * @dataProvider getValuesToHex
     * @param string|null $value
     * @param string|null $hexEncoded
     */
    public function testCastToHex(?string $value, ?string $hexEncoded): void
    {
        $this->assertEquals($hexEncoded, $this->caster->output($value));
    }

    public function getValuesFrom(): array
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

    public function getValuesToHex(): array
    {
        return [
            [null,         null],
            ['',           '\x'],
            ["\000'\\",    '\x00275c'],
            ['ABC',        '\x414243']
        ];
    }

    public function invalidPHPValuesProvider(): array
    {
        return [
            [[]],
            [1.234],
            [new \stdClass()]
        ];
    }
}
