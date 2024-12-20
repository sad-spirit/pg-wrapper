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
    types\Path
};

/**
 * Converter for path data type
 */
class PathConverter extends BaseGeometricConverter
{
    protected function parseInput(string $native, int &$pos): Path
    {
        $points = $this->parsePoints($native, $pos, $this->countPoints($native), true, $usedSquare);
        return new Path($usedSquare ?? false, ...$points);
    }

    protected function outputNotNull(mixed $value): string
    {
        if (\is_array($value)) {
            $value = Path::createFromArray($value);
        } elseif (!$value instanceof Path) {
            throw TypeConversionException::unexpectedValue($this, 'output', 'instance of Path or an array', $value);
        }

        $points = [];
        foreach ($value as $point) {
            $points[] = $this->point->output($point);
        }
        return ($value->open ? '[' : '(') . \implode(',', $points) . ($value->open ? ']' : ')');
    }

    public function dimensions(): int
    {
        return 2;
    }
}
