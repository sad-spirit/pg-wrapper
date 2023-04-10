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

use sad_spirit\pg_wrapper\converters\StringConverter;

/**
 * Unit test for string type(s) converter
 */
class StringTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new StringConverter();
    }

    public function valuesBoth(): array
    {
        return [
            [null, null],
            ['', ''],
            ['1', '1'],
            ['2.324', '2.324'],
            ['text', 'text'],
        ];
    }

    public function valuesFrom(): array
    {
        return [];
    }

    public function valuesTo(): array
    {
        return [
            ['1', 1.0],
            ['-3', -3.00]
        ];
    }
}
