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

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_wrapper\exceptions\BadMethodCallException;
use sad_spirit\pg_wrapper\types\Point;

class PointListTest extends TestCase
{
    public function testIsAList(): void
    {
        $input = ['foo' => new Point(0, 0), 'bar' => new Point(1, 1), 'baz' => new Point(2, 3)];

        $list = new PointListImplementation(...$input);

        $this::assertTrue(isset($list[0]));
        $this::assertFalse(isset($list['foo']));

        $this::assertEquals(
            [new Point(0, 0), new Point(1, 1), new Point(2, 3)],
            $list->getIterator()->getArrayCopy()
        );
    }

    public function testCannotOffsetSet(): void
    {
        $list = new PointListImplementation(new Point(0, 0));

        $this::expectException(BadMethodCallException::class);
        $list[1] = new Point(1, 1);
    }

    public function testCannotOffsetUnset(): void
    {
        $list = new PointListImplementation(new Point(0, 0));

        $this::expectException(BadMethodCallException::class);
        unset($list[0]);
    }
}
