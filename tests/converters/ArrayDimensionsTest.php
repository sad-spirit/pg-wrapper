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
use sad_spirit\pg_wrapper\converters\containers\ArrayConverter;
use sad_spirit\pg_wrapper\converters\IntegerConverter;
use sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Postgres may return an array literal with dimensions specified
 *
 * @link https://github.com/sad-spirit/pg-wrapper/issues/12
 */
class ArrayDimensionsTest extends TestCase
{
    private ArrayConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new ArrayConverter(new IntegerConverter());
    }

    #[DataProvider('invalidDimensions')]
    public function testInvalidDimensionsSpecifications(string $invalid, string $message): void
    {
        $this::expectException(TypeConversionException::class);
        $this::expectExceptionMessage($message);

        $this->converter->input($invalid);
    }

    #[DataProvider('mismatchedDimensions')]
    public function testDimensionsMismatch(string $invalid): void
    {
        $this::expectException(TypeConversionException::class);
        $this::expectExceptionMessage('do not match');

        $this->converter->input($invalid);
    }

    #[DataProvider('validDimensions')]
    public function testValidDimensions(string $input, array $expected): void
    {
        $this::assertEquals($expected, $this->converter->input($input));
    }

    public static function invalidDimensions(): array
    {
        return [
            ['[1',          "expecting ']'"],
            ['[1]{1}',      "expecting '='"],
            ['[ 1 ]={1}',   "expecting array bounds after '['"],
            ['[2:1]={1,2}', "upper bound (1) cannot be less"],
            ['[-1]={1}',    "upper bound (-1) cannot be less"]
        ];
    }

    public static function mismatchedDimensions(): array
    {
        return [
            ['[1]={{2}}'],
            ['[1][1]={2}'],
            ['[1]={2,3}'],
            ['[2:3]={4,5,6}'],
            ['[2][2]={{1},{1}}']
        ];
    }

    public static function validDimensions(): array
    {
        return [
            ['[1]={2}',                     [2]],
            ['[-1:-1]={2}',                 [-1 => 2]],
            ['[0:1]={1,2}',                 [1, 2]],
            ['[2][2]={{1,2},{3,4}}',        [[1, 2], [3, 4]]],
            ['[1:2] [3:4] = {{1,2},{3,4}}', [1 => [3 => 1, 4 => 2], 2 => [3 => 3, 4 => 4]]]
        ];
    }
}
