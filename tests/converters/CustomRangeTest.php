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

use sad_spirit\pg_wrapper\converters\containers\RangeConverter;
use sad_spirit\pg_wrapper\converters\StringConverter;
use sad_spirit\pg_wrapper\exceptions\InvalidArgumentException;
use sad_spirit\pg_wrapper\tests\types\CustomRange;

/**
 * Unit test for converting custom range type to custom Range subclass
 *
 * @extends TypeConverterTestCase<RangeConverter>
 */
class CustomRangeTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new RangeConverter(new StringConverter(), CustomRange::class);
    }

    public static function valuesBoth(): array
    {
        return [
            ['empty',  new CustomRange(empty: true)],
            ['(,)',    new CustomRange()],
            ['("A",)', new CustomRange('a', null, false, true)],
            ['(,"Z")', new CustomRange(null, 'z', false, false)]
        ];
    }

    public static function valuesFrom(): array
    {
        return [
            [' ( empty, empty )  ', new CustomRange(' empty', ' empty ', false, false)]
        ];
    }

    public static function valuesTo(): array
    {
        return [
            ['empty',                        ['empty' => true]],
            ['["A","Z")',                    ['a', 'z']],
            ['["A","Z"]',                    ['lower' => 'a', 'upper' => 'z', 'upperInclusive' => true]],
            ['empty',                        ['a', 'A']],
            [new InvalidArgumentException(), ['z', 'a']]
        ];
    }
}
