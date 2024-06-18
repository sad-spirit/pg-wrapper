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
    /** @var ArrayConverter */
    private $converter;

    protected function setUp(): void
    {
        $this->converter = new ArrayConverter(new IntegerConverter());
    }

    /**
     * @dataProvider invalidDimensions
     */
    public function testInvalidDimensionsSpecifications(string $invalid, string $message): void
    {
        $this::expectException(TypeConversionException::class);
        $this::expectExceptionMessage($message);

        $this->converter->input($invalid);
    }

    /**
     * @dataProvider mismatchedDimensions
     */
    public function testDimensionsMismatch(string $invalid): void
    {
        $this::expectException(TypeConversionException::class);
        $this::expectExceptionMessage('do not match');

        $this->converter->input($invalid);
    }

    /**
     * @dataProvider validDimensions
     */
    public function testValidDimensions(string $input, array $expected): void
    {
        $this::assertEquals($expected, $this->converter->input($input));
    }

    public function invalidDimensions(): array
    {
        return [
            ['[1',          "expecting ']'"],
            ['[1]{1}',      "expecting '='"],
            ['[ 1 ]={1}',   "expecting array bounds after '['"],
            ['[2:1]={1,2}', "cannot be less"],
            ['[-1]={1}',    "cannot be less"]
        ];
    }

    public function mismatchedDimensions(): array
    {
        return [
            ['[1]={{2}}'],
            ['[1][1]={2}'],
            ['[1]={2,3}'],
            ['[2:3]={4,5,6}'],
            ['[2][2]={{1},{1}}']
        ];
    }

    public function validDimensions(): array
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
