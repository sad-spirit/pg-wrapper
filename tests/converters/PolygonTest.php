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

use sad_spirit\pg_wrapper\converters\geometric\PolygonConverter;
use sad_spirit\pg_wrapper\{
    exceptions\TypeConversionException,
    exceptions\InvalidArgumentException,
    types\Point,
    types\Polygon
};

/**
 * Unit test for 'polygon' geometric type converter
 */
class PolygonTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new PolygonConverter();
    }

    public function valuesBoth(): array
    {
        return [
            [null, null],
            ['((1,2))', new Polygon(new Point(1, 2))],
            ['((1,2),(1.2,2.3))', new Polygon(new Point(1, 2), new Point(1.2, 2.3))],
        ];
    }

    public function valuesFrom(): array
    {
        return [
            ['1, 2,  3  , 4 ', new Polygon(new Point(1, 2), new Point(3, 4))],
            ['(1, 2, 3,4)',    new Polygon(new Point(1, 2), new Point(3, 4))],
            ['(1, 2',          new TypeConversionException()],
            ['[(1,2)]',        new TypeConversionException()]
        ];
    }

    public function valuesTo(): array
    {
        return [
            ['((1,2))',                      [[1, 2]]],
            ['((3,4))',                      [new Point(3, 4)]],
            [new TypeConversionException(),  1],
            [new InvalidArgumentException(), ['point']],
            [new InvalidArgumentException(), [[1]]],
            [new InvalidArgumentException(), [[1, 1, 1]]],
            [new \TypeError(),               [[2, 'string'], null]],
            [new InvalidArgumentException(), [null, [[1, 2]]]],
        ];
    }
}
