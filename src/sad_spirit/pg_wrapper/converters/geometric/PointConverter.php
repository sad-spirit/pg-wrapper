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
    converters\ContainerConverter,
    converters\FloatConverter,
    exceptions\TypeConversionException,
    types\Point
};

/**
 * Converter for point type, building block for other geometric types
 */
class PointConverter extends ContainerConverter
{
    /** Converter for point's coordinates */
    private readonly FloatConverter $floatConverter;

    public function __construct()
    {
        $this->floatConverter = new FloatConverter();
    }

    protected function parseInput(string $native, int &$pos): Point
    {
        $hasDelimiters = false;
        if ('(' === $this->nextChar($native, $pos)) {
            $hasDelimiters = true;
            $pos++;
        }
        $len  = \strcspn($native, ',)', $pos);
        $x    = \substr($native, $pos, $len);
        $pos += $len;

        $this->expectChar($native, $pos, ',');

        $len  = \strcspn($native, ',)', $pos);
        $y    = \substr($native, $pos, $len);
        $pos += $len;

        if ($hasDelimiters) {
            $this->expectChar($native, $pos, ')');
        }
        return new Point($this->floatConverter->input($x), $this->floatConverter->input($y));
    }

    protected function outputNotNull(mixed $value): string
    {
        if (\is_array($value)) {
            $value = Point::createFromArray($value);
        } elseif (!$value instanceof Point) {
            throw TypeConversionException::unexpectedValue($this, 'output', 'instance of Point or an array', $value);
        }
        return '(' . $this->floatConverter->output($value->x) . ',' . $this->floatConverter->output($value->y) . ')';
    }

    public function dimensions(): int
    {
        return 1;
    }
}
