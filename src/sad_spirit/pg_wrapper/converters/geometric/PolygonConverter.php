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
    types\Polygon
};

/**
 * Converter for polygon data type
 */
class PolygonConverter extends BaseGeometricConverter
{
    protected function parseInput(string $native, int &$pos): Polygon
    {
        return new Polygon(...$this->parsePoints($native, $pos, $this->countPoints($native)));
    }

    protected function outputNotNull(mixed $value): string
    {
        if (\is_array($value)) {
            $value = Polygon::createFromArray($value);
        } elseif (!$value instanceof Polygon) {
            throw TypeConversionException::unexpectedValue($this, 'output', 'instance of Polygon or an array', $value);
        }

        $points = [];
        foreach ($value as $point) {
            $points[] = $this->point->output($point);
        }
        return '(' . \implode(',', $points) . ')';
    }

    public function dimensions(): int
    {
        return 2;
    }
}
