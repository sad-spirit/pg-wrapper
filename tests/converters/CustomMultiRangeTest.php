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

use sad_spirit\pg_wrapper\converters\containers\MultiRangeConverter;
use sad_spirit\pg_wrapper\converters\StringConverter;
use sad_spirit\pg_wrapper\exceptions\InvalidArgumentException;
use sad_spirit\pg_wrapper\tests\types\CustomMultiRange;
use sad_spirit\pg_wrapper\tests\types\CustomRange;
use sad_spirit\pg_wrapper\types\Range;

/**
 * Unit test for converting custom multirange type to custom MultiRange subclass
 *
 * @extends TypeConverterTestCase<MultiRangeConverter>
 */
class CustomMultiRangeTest extends TypeConverterTestCase
{
    protected function setUp(): void
    {
        $this->converter = new MultiRangeConverter(new StringConverter(), CustomMultiRange::class);
    }

    public static function valuesBoth(): array
    {
        return [
            [
                '{}',
                new CustomMultiRange()
            ],
            [
                '{(,)}',
                new CustomMultiRange(new CustomRange())],
            [
                '{["A","D"],("X","Z")}',
                new CustomMultiRange(
                    new CustomRange('A', 'd', true, true),
                    new CustomRange('x', 'Z', false, false)
                )
            ]
        ];
    }

    public static function valuesFrom(): array
    {
        return [];
    }

    public static function valuesTo(): array
    {
        return [
            ['{["A","Z")}',                  [['a', 'z']]],
            ['{["A","Z"]}',                  [['lower' => 'a', 'upper' => 'z', 'upperInclusive' => true]]],
            ['{empty}',                      [['a', 'A']]],
            [new InvalidArgumentException(), [new Range('a', 'z')]],
            [new InvalidArgumentException(), [['z', 'a']]]
        ];
    }
}
