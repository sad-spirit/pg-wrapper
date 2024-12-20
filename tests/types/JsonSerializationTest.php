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

namespace sad_spirit\pg_wrapper\tests\types;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use sad_spirit\pg_wrapper\types\{
    ArrayRepresentable,
    Box,
    Circle,
    DateTimeMultiRange,
    DateTimeRange,
    Line,
    LineSegment,
    NumericRange,
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
    #[DataProvider('typeInstances')]
    public function testSerializesToJson(ArrayRepresentable $object): void
    {
        $this::assertEquals(
            $object,
            $object::createFromArray(\json_decode(\json_encode($object), true))
        );
    }

    /**
     * Provides data for testSerializesToJson() method
     * @return array<array<ArrayRepresentable>>
     */
    public static function typeInstances(): array
    {
        return [
            [new Box(new Point(1.2, 3.4), new Point(5.6, 7.8))],
            [new Circle(new Point(1.2, 3.4), 5.6)],
            [new Line(1.2, 3.4, 5.6)],
            [new LineSegment(new Point(1.2, 3.4), new Point(5.6, 7.8))],
            [new Path(true, new Point(1, 2), new Point(1.2, 2.3))],
            [new Point(1.2, 3.4)],
            [new Polygon(new Point(1, 2), new Point(3, 4))],
            [new Tid(3, 4)],
            [NumericRange::createEmpty()],
            [new NumericRange(1.2, 3.4, false, true)],
            [DateTimeRange::createEmpty()],
            [new DateTimeRange(
                new \DateTimeImmutable('2022-11-29T17:17:32Z'),
                new \DateTimeImmutable('now'),
                true,
                true
            )],
            [new DateTimeMultiRange(
                DateTimeRange::createEmpty(),
                new DateTimeRange(
                    new \DateTimeImmutable('2022-11-29T17:17:32Z'),
                    new \DateTimeImmutable('now'),
                    true,
                    true
                )
            )]
        ];
    }
}
