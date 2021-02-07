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

use sad_spirit\pg_wrapper\converters\geometric\PathConverter;
use sad_spirit\pg_wrapper\{
    exceptions\InvalidArgumentException,
    exceptions\TypeConversionException,
    types\Path,
    types\Point
};

/**
 * Unit test for 'path' geometric type converter
 */
class PathTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new PathConverter();
    }

    public function valuesBoth(): array
    {
        return [
            [null,                null],
            ['[(1,2)]',           new Path([new Point(1, 2)], true)],
            ['[(1,2),(1.2,2.3)]', new Path([new Point(1, 2), new Point(1.2, 2.3)], true)],
            ['((1,2),(1.2,2.3))', new Path([new Point(1, 2), new Point(1.2, 2.3)], false)]
        ];
    }

    public function valuesFrom(): array
    {
        return [
            ['1,2,3,4',     new Path([new Point(1, 2), new Point(3, 4)], false)],
            ['(1,2,3,4,5)', new TypeConversionException()],
            ['([1,2],3,4)', new TypeConversionException()],
        ];
    }

    public function valuesTo(): array
    {
        return [
            ['((1,2),(1.2,2.3))',            [[1, 2], [1.2, 2.3]]],
            ['[(3,4),(5,6)]',                ['open' => true, [3, 4], [5, 6]]],
            [new TypeConversionException(),  1],
            [new InvalidArgumentException(), ['point']],
            [new InvalidArgumentException(), [[1]]],
            [new InvalidArgumentException(), [[1, 1, 1]]],
            [new \TypeError(),               [[2, 'string'], null]],
            [new InvalidArgumentException(), [null, [[1, 2]]]],
        ];
    }
}
