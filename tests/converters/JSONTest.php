<?php
/**
 * Wrapper for PHP's pgsql extension providing conversion of complex DB types
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-wrapper/master/LICENSE
 *
 * @package   sad_spirit\pg_wrapper
 * @copyright 2014-2020 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\tests\converters;

use sad_spirit\pg_wrapper\{
    converters\JSONConverter,
    exceptions\TypeConversionException
};

/**
 * Unit test for JSON (and JSONB) type converter
 */
class JSONTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new JSONConverter();
    }

    public function testJSONBigintAsString()
    {
        $this->assertSame(
            ['largenum' => '123456789012345678901234567890'],
            $this->converter->input('{"largenum":123456789012345678901234567890}')
        );
    }

    public function testInvalidUTF8Sequence()
    {
        $this->expectException(TypeConversionException::class);
        $this->converter->output("\xB1\x31");
    }

    protected function valuesBoth()
    {
        return [
            [null,                         null],
            ['false',                      false],
            ['"\u0442\u0435\u0441\u0442"', 'тест'],
            ['{"foo":"bar"}',              ['foo' => 'bar']]
        ];
    }

    protected function valuesFrom()
    {
        return [
            ['"тест"',       'тест'],
            ['[1}',          new TypeConversionException()],
            ["\"\xB1\x31\"", new TypeConversionException()]
        ];
    }

    protected function valuesTo()
    {
        $foo = new \stdClass();
        $foo->bar = $foo;

        return [
            [new TypeConversionException(), $foo],
            [new TypeConversionException(), fopen(__DIR__ . '/TypeConverterTestCase.php', 'rb')],
            [new TypeConversionException(), [NAN]]
        ];
    }
}
