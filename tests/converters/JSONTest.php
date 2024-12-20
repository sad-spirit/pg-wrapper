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

use sad_spirit\pg_wrapper\{
    converters\JSONConverter,
    exceptions\TypeConversionException
};

/**
 * Unit test for JSON (and JSONB) type converter
 *
 * @extends TypeConverterTestCase<JSONConverter>
 */
class JSONTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new JSONConverter();
    }

    public function testJSONBigintAsString(): void
    {
        $this->assertSame(
            ['largenum' => '123456789012345678901234567890'],
            $this->converter->input('{"largenum":123456789012345678901234567890}')
        );
    }

    public function testInvalidUTF8Sequence(): void
    {
        $this->expectException(TypeConversionException::class);
        $this->converter->output("\xB1\x31");
    }

    public static function valuesBoth(): array
    {
        return [
            [null,                         null],
            ['false',                      false],
            ['"\u0442\u0435\u0441\u0442"', 'тест'],
            ['{"foo":"bar"}',              ['foo' => 'bar']]
        ];
    }

    public static function valuesFrom(): array
    {
        return [
            ['"тест"',       'тест'],
            ['[1}',          new TypeConversionException()],
            ["\"\xB1\x31\"", new TypeConversionException()]
        ];
    }

    public static function valuesTo(): array
    {
        $foo = new \stdClass();
        $foo->bar = $foo;

        return [
            [new TypeConversionException(), $foo],
            [new TypeConversionException(), \fopen(__DIR__ . '/TypeConverterTestCase.php', 'rb')],
            [new TypeConversionException(), [\NAN]]
        ];
    }
}
