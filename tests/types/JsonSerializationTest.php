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

namespace sad_spirit\pg_wrapper\tests\types;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_wrapper\types\{
    ArrayRepresentable,
    Box,
    Circle,
    Line,
    LineSegment,
    Path,
    Point,
    Polygon,
    Tid
};

/**
 * Checks that objects representing PostgreSQL types can be encoded to JSON and decoded back
 *
 * @link https://github.com/sad-spirit/pg-wrapper/issues/11
 */
class JsonSerializationTest extends TestCase
{
    /**
     * @dataProvider typeInstances
     * @param ArrayRepresentable $object
     * @return void
     */
    public function testSerializesToJson(ArrayRepresentable $object): void
    {
        $this::assertEquals(
            $object,
            $object::createFromArray(json_decode(json_encode($object), true))
        );
    }

    /**
     * Provides data for testSerializesToJson() method
     * @return array<array<ArrayRepresentable>>
     */
    public function typeInstances(): array
    {
        return [
            [new Box(new Point(1.2, 3.4), new Point(5.6, 7.8))],
            [new Circle(new Point(1.2, 3.4), 5.6)],
            [new Line(1.2, 3.4, 5.6)],
            [new LineSegment(new Point(1.2, 3.4), new Point(5.6, 7.8))],
            [new Path(true, new Point(1, 2), new Point(1.2, 2.3))],
            [new Point(1.2, 3.4)],
            [new Polygon(new Point(1, 2), new Point(3, 4))],
            [new Tid(3, 4)]
        ];
    }
}
