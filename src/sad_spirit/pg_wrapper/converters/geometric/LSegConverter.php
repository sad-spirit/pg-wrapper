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

namespace sad_spirit\pg_wrapper\converters\geometric;

use sad_spirit\pg_wrapper\{
    exceptions\TypeConversionException,
    types\LineSegment
};

/**
 * Converter for line segment type, represented by a pair of points
 */
class LSegConverter extends BaseGeometricConverter
{
    protected function parseInput(string $native, int &$pos): LineSegment
    {
        return new LineSegment(...$this->parsePoints($native, $pos, 2, true));
    }

    protected function outputNotNull(mixed $value): string
    {
        if (\is_array($value)) {
            $value = LineSegment::createFromArray($value);
        } elseif (!$value instanceof LineSegment) {
            throw TypeConversionException::unexpectedValue(
                $this,
                'output',
                'instance of LineSegment or an array',
                $value
            );
        }
        return '[' . $this->point->output($value->start) . ',' . $this->point->output($value->end) . ']';
    }

    public function dimensions(): int
    {
        return 2;
    }
}
