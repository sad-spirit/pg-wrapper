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
    types\Line
};

/**
 * Converter for line type, represented by three coefficients of linear equation (PostgreSQL 9.4+)
 */
class LineConverter extends ContainerConverter
{
    /** Converter for line's coefficients */
    private readonly FloatConverter $floatConverter;

    public function __construct()
    {
        $this->floatConverter = new FloatConverter();
    }

    protected function parseInput(string $native, int &$pos): Line
    {
        $this->expectChar($native, $pos, '{');

        $len  = \strcspn($native, ',}', $pos);
        $A    = \substr($native, $pos, $len);
        $pos += $len;

        $this->expectChar($native, $pos, ',');

        $len  = \strcspn($native, ',}', $pos);
        $B    = \substr($native, $pos, $len);
        $pos += $len;

        $this->expectChar($native, $pos, ',');

        $len  = \strcspn($native, ',}', $pos);
        $C    = \substr($native, $pos, $len);
        $pos += $len;

        $this->expectChar($native, $pos, '}');

        return new Line(
            $this->floatConverter->input($A),
            $this->floatConverter->input($B),
            $this->floatConverter->input($C)
        );
    }

    protected function outputNotNull(mixed $value): string
    {
        if (\is_array($value)) {
            $value = Line::createFromArray($value);
        } elseif (!$value instanceof Line) {
            throw TypeConversionException::unexpectedValue($this, 'output', 'instance of Line or an array', $value);
        }
        return '{' . $this->floatConverter->output($value->A) . ',' . $this->floatConverter->output($value->B)
               . ',' . $this->floatConverter->output($value->C) . '}';
    }

    public function dimensions(): int
    {
        return 1;
    }
}
