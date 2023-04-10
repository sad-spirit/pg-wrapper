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

use sad_spirit\pg_wrapper\converters\containers\ArrayConverter;
use sad_spirit\pg_wrapper\converters\geometric\PointConverter;
use sad_spirit\pg_wrapper\{
    exceptions\TypeConversionException,
    exceptions\InvalidArgumentException,
    types\Point
};

/**
 * Unit test for a combination of array and point type converters
 */
class PointArrayTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new ArrayConverter(new PointConverter());
    }

    public function valuesBoth(): array
    {
        return [
            [null, null],
            ['{}', []],
            ['{NULL,NULL}', [null, null]],
            ['{NULL,"(0.6,0.3)"}', [null, new Point(0.6, 0.3)]],
            ['{"(0,0)","(1,2)"}', [new Point(0, 0), new Point(1, 2)]],
            ['{"(0.6,0.3)"}', [new Point(0.6, 0.3)]],
        ];
    }

    public function valuesFrom(): array
    {
        return [
            ['{NULL, NULL}', [null, null]],
            ['{ {NULL} ,{  NULL," (0,   0)" }}  ', [[null], [null, new Point(0, 0)]]],
        ];
    }

    public function valuesTo(): array
    {
        return [
            ['{"(1,2)","(3,4)"}',           [['y' => 2, 'x' => 1], [3, 4]]],
            [new TypeConversionException(), 1],
            [new TypeConversionException(), ['point']],
            [new InvalidArgumentException(), [[1]]],
            [new InvalidArgumentException(), [[1, 1, 1]]],
            [new \TypeError(),               [[2, 'string'], null]],
            [new InvalidArgumentException(), [null, [[1, 2]]]],
            // the result is accepted by Postgres but probably shouldn't be
            // http://www.postgresql.org/message-id/E1VEETa-0007KM-8O@wrigleys.postgresql.org
            [new TypeConversionException(), [[null], [null, [0, 0]]]],
            // empty sub-arrays
            [new InvalidArgumentException(), [[]]],
            [new InvalidArgumentException(), [[], []]]
        ];
    }
}
