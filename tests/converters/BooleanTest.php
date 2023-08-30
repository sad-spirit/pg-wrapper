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
 * @copyright 2014-2023 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\tests\converters;

use sad_spirit\pg_wrapper\converters\BooleanConverter;

/**
 * Unit test for boolean type converter
 *
 * @extends TypeConverterTestCase<BooleanConverter>
 */
class BooleanTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new BooleanConverter();
    }

    public function valuesBoth(): array
    {
        return [
            [null, null],
            ['t', true],
            ['f', false],
        ];
    }

    public function valuesFrom(): array
    {
        return [
            ['1', true],
            ['0', false],
        ];
    }

    public function valuesTo(): array
    {
        return [
            ['t', 'true'],
            ['t', 1],
            ['t', -1],
            ['t', '1'],
            ['t', '1.1'],
            ['t', '0.0'],
            ['t', 'string'],
            ['t', ['value']],
            ['t', [0]],

            ['f', 'false'],
            ['f', 0],
            ['f', '0'],
            ['f', []],
        ];
    }
}
